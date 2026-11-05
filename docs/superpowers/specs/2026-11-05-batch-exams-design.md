# Batch Exams (section-wise pass → certificate) — Design

Date: 2026-11-05
Branch: feature/student-certificates (continues the certificate work)
Status: approved in brainstorming; implementation pending.

## Goal

Let an Admin set up an exam for a batch with four fixed CEFR sections
(Hören / Lesen / Schreiben / Sprechen), have the batch teacher (or Admin) enter
each student's per-section marks, compute a **section-wise pass** (student passes
only when **every** section is passed), and from a passing result issue the
existing A4 course-completion certificate — with the certificate now linked to
the result and revocable.

## Decisions (from brainstorming)

1. **Sections:** four fixed section names (Hören, Lesen, Schreiben, Sprechen).
   Per exam, Admin may edit each section's `max_marks` and `passing_marks`; names
   stay fixed. (Q1 = c)
2. **Overall pass rule:** a student passes the exam only if **all four** sections
   are passed. Fail any one → overall fail. (Q2 = a)
3. **Certificate link:** on a passing result an Admin-only "Issue Certificate"
   action appears, prefilled (student name, level from the batch's course) and
   feeding the existing certificate flow; the certificate stores the result id.
   The direct manual certificate path (current behaviour) remains. (Q3 = a)
4. **Access:** Admin full; **teacher of the batch** may enter/edit results for
   own-batch exams but cannot create exams or issue certificates; Admin creates
   exams. Student views own result only. (Q4 = b)
5. **Correction guard:** editing marks that flip a result **pass → fail** is
   **blocked** while a non-revoked certificate is linked to it; the Admin must
   revoke that certificate first. (Q5 = a)
6. **Revoke:** Admin can revoke an issued certificate with a required reason; the
   revoked certificate shows a "REVOKED" stamp on its print page, is marked in the
   issued list, no longer counts as the student's valid certificate, and is never
   deleted. (Q6 = yes)
7. **Cardinality:** many exams per batch (mid-term, final, retake); a student may
   have one result per exam; a certificate may issue off any passing result. (Q7 = a)

## Pattern alignment

The certificate branch created a fresh `student_certificates` table and left the
legacy `Certificate` model/table dormant. This feature follows the same approach:
**new clean tables**, and the legacy empty `exams` / `exam_results` tables and
their models (whole-exam `max_marks`/`passing_marks`, single `marks_obtained`) are
left untouched — their schema cannot represent section-wise pass. New exam tables
use distinct names to avoid clashing with the legacy `exams` table.

## Data model (new, forward-only migrations)

### `batch_exams`
- `id`
- `batch_id` → `batches.id`, `cascadeOnDelete` is **not** used; restrict/`nullOnDelete`
  is avoided because an exam without a batch is meaningless — use a plain indexed
  `foreignId('batch_id')->constrained()` with `restrictOnDelete()`.
- `name` (string, e.g. "A1 Final")
- `type` (string: `final` | `mid` | `retake` | `other`; default `final`)
- `exam_date` (date)
- `status` (string: `scheduled` | `completed` | `cancelled`; default `scheduled`)
- timestamps

### `exam_sections`
- `id`
- `batch_exam_id` → `batch_exams.id`, `cascadeOnDelete`
- `name` (string: Hören | Lesen | Schreiben | Sprechen)
- `max_marks` (decimal 6,2)
- `passing_marks` (decimal 6,2)
- `sort` (unsigned tiny int, 1..4)
- unique (`batch_exam_id`, `name`)

### `student_exam_results`
- `id`
- `batch_exam_id` → `batch_exams.id`, `cascadeOnDelete`
- `student_id` → `users.id`, `restrictOnDelete`
- `overall_passed` (boolean)
- `graded_by` → `users.id`, `nullOnDelete`
- `graded_at` (timestamp)
- timestamps
- unique (`batch_exam_id`, `student_id`)

### `student_exam_section_marks`
- `id`
- `student_exam_result_id` → `student_exam_results.id`, `cascadeOnDelete`
- `exam_section_id` → `exam_sections.id`, `restrictOnDelete`
- `marks_obtained` (decimal 6,2)
- `passed` (boolean)
- unique (`student_exam_result_id`, `exam_section_id`)

### `student_certificates` (modify existing table)
- add `student_exam_result_id` (nullable) → `student_exam_results.id`, `nullOnDelete`
- add `revoked_at` (nullable timestamp)
- add `revoked_by` (nullable) → `users.id`, `nullOnDelete`
- add `revoke_reason` (nullable string, max 1000)

## Models

- `BatchExam`: `belongsTo(Batch)`, `hasMany(ExamSection)`, `hasMany(StudentExamResult)`.
  Helper `seedDefaultSections(array $marks)` creating the four sections. The four
  default section names live in a `const SECTIONS = ['Hören','Lesen','Schreiben','Sprechen']`.
- `ExamSection`: `belongsTo(BatchExam)`.
- `StudentExamResult`: `belongsTo(BatchExam)`, `belongsTo(User,'student_id')`,
  `hasMany(StudentExamSectionMark)`, `hasOne(StudentCertificate)`,
  `belongsTo(User,'graded_by')`. Casts `overall_passed` bool, `graded_at` datetime.
- `StudentExamSectionMark`: `belongsTo(StudentExamResult)`, `belongsTo(ExamSection)`.
  Casts `passed` bool, marks decimal:2.
- `StudentCertificate` (existing): add relation `result()`
  `belongsTo(StudentExamResult)` and `issuer`/existing stay; add
  `scopeActive` = `whereNull('revoked_at')`; add `isRevoked()`.

## Services / domain logic

A small `ExamGradingService` (or static helpers on the models) centralises:
- **Per-section pass:** `marks_obtained >= passing_marks`.
- **Overall pass:** every section mark `passed === true` AND all four sections
  have a mark recorded (a missing section ⇒ not passed).
- **Save results** for one student: validate each section mark is `0 <= marks <= max_marks`,
  compute `passed` per section and `overall_passed`, persist result + section marks
  in one transaction, set `graded_by`/`graded_at`, write an audit row
  (`exam.result_graded`).
- **Correction guard:** before overwriting, if the existing result is
  `overall_passed === true` and has a linked non-revoked certificate, and the new
  computation would be `overall_passed === false`, reject with a clear error
  ("revoke certificate <number> first"). The edit is blocked; nothing is written.

Certificate changes (extend existing `CertificateController` / flow):
- **Issue from result:** when `student_exam_result_id` is supplied, require the
  result exists and `overall_passed === true`; prefill level from the result's
  `batchExam.batch.course.level`; store the link.
- **Duplicate guard:** block issuing a second *active* (non-revoked) certificate
  for the same `student_id` + `level`.
- **Revoke:** `revoke(StudentCertificate)` — Admin only, reason 5..1000 chars,
  sets `revoked_at`/`revoked_by`/`revoke_reason`, audit `certificate.revoked`.
  Idempotent: revoking an already-revoked cert is rejected.
- Print view: when revoked, overlay a "REVOKED" stamp and show the reason; the
  issued list marks revoked rows.

## Access control

- `batch_exams` create/edit, section structure, certificate issue/revoke: Admin only.
- Result enter/edit: Admin, or the batch's assigned teacher for that batch's exams.
- Result/exam view: Admin; teacher for own batches; student for own results only.
- Enforced with existing `role:admin` middleware plus per-row teacher-batch checks
  mirroring the current attendance/payroll controllers.
- A result's student must be an active or completed enrollee of the exam's batch.

## Pages (Blade, reuse existing components)

- **Exams index** (`/exams`, optional `?batch=`): list of exams, filter by batch,
  create button (Admin). Teacher sees only own-batch exams.
- **Create/edit exam form:** batch select, name, type, date; four section rows with
  editable max + passing marks (defaults filled). Admin only.
- **Exam show:** exam meta; roster of the batch's eligible students with a marks
  grid (one column per section) to enter/save; each row shows per-section pass and
  overall pass; a passing row shows "Issue Certificate" (Admin) or the linked
  certificate number if already issued (with Revoke for Admin).
- **Student "My Results":** list of own results with per-section marks and pass/fail;
  link to their certificate when issued.
- Sidebar: "Exams" for Admin and Teacher (scoped); "My Results" for Student.
- Uses `layouts.admin`, existing list/show/`data-confirm` patterns; no new JS libs.

## Error handling

- Marks outside `0..max_marks` → validation error, nothing saved.
- Non-enrollee student → rejected.
- Teacher acting on another batch's exam → 403.
- Correction flip while cert live → blocked with the revoke-first message.
- Duplicate active cert → blocked naming the existing certificate number.
- Revoke without reason / already revoked → validation error.

## Testing (PHPUnit, SQLite :memory:, RefreshDatabase)

New `tests/Feature/BatchExamTest.php` and additions to `CertificateTest.php`:
- exam create seeds four sections with editable marks; names fixed.
- per-section pass boundary (equal to passing = pass); overall pass needs all four;
  one section fail ⇒ overall fail; missing section ⇒ not passed.
- marks validation (negative, above max) rejected.
- only enrollees can be graded.
- teacher enters results for own batch; 403 on another batch; teacher cannot create
  exam or issue certificate.
- student sees own result only; not another's.
- issuing a certificate from a passing result links `student_exam_result_id` and
  prefills level from the batch course.
- duplicate active certificate for same student+level blocked.
- correction that flips pass→fail blocked while a non-revoked linked cert exists;
  after revoking the cert, the same edit succeeds.
- revoke sets fields + audit; revoked cert print shows REVOKED; already-revoked
  revoke rejected.
- audit rows for grade, issue, revoke; immutability where applicable.
- full suite runs green (note the pre-existing date-dependent BatchScheduleTest
  caveat recorded in docs/TASKS.md is unrelated to this branch).

## Out of scope (YAGNI)

- No weighting/percentage overall rule (chose all-sections pass).
- No configurable/added section names beyond the fixed four.
- No auto-issue of certificates (manual Admin click retained).
- No PDF package (browser print as already built).
- No exam scheduling/notifications, no grade letters, no retake-attempt limits.
- Legacy `exams`/`exam_results` tables/models remain dormant, untouched.
