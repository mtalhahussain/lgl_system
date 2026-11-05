# Student certificates (template overlay) — design

Date: 2026-11-05
Status: Approved (brainstorming), pending implementation plan

## Context

The owner wants to issue a course-completion certificate to a student. The
earlier E01 discovery (docs/TASKS.md, 3 November 2026) sketched an exam/marks
driven flow with an editable field template. The owner has since narrowed the
decision:

- **Issuance is an Admin decision** — not gated by an exam/marks engine. The
  Admin decides who receives a certificate. (No exams, results, or passing
  rules are built.)
- The visual design is a **fixed background image** the owner supplies at
  `storage/app/public/settings/template.png`. The app overlays only the text.

This design supersedes E01's exam-based and field-editor proposals for the
certificate feature. The legacy `exams`, `exam_results`, and `certificates`
tables and models are **not** used (see "Legacy tables" below).

## The template

`storage/app/public/settings/template.png` is an A4 **landscape** background
(29.7 × 21 cm). It already contains the branding and is used **as-is**:

- Top-left logo ("Land of German Language")
- German-flag watercolour accent down the left edge
- Red decorative bars (top-right, bottom-left)
- A printed **signature "Mayoor Ali Khan, CEO"** (bottom-centre)
- A round institute **seal** (bottom-centre-right)

The template carries **no body text**. The empty centre/right area is where the
application renders the certificate text. The signature is part of the image, so
no signature upload is built.

## Decision

Render each certificate as an HTML page sized to A4 landscape, with
`template.png` as a full-bleed background and a single fixed-position text block
overlaid on top. The Admin supplies the student, father's name, level, and
completion date; the rest of the text is fixed. Printing is done from the
browser (`@page { size: A4 landscape }`). No PDF package is added.

### Overlaid text block (matches the owner's reference image)

```
Teilnahmebescheinigung                         (title)

Herzlichen Glückwunsch, {student} S/O {father} (red, bold italic)
zum erfolgreichen Abschluss des
Deutsch-Sprachkurses – Niveau {level}           (bold)

Kurs abgeschlossen am {date}                     ({date} bold)

Ihr Einsatz beim Erlernen der deutschen Sprache war lobenswert.
Auf dem {level}-Niveau haben Sie grundlegende sprachliche
Fähigkeiten erworben und erste einfache Kommunikationssituationen
sicher gemeistert.                               (fixed body paragraph)
```

Variables: `{student}`, `{father}`, `{level}`, `{date}`. The title, the German
boilerplate lines, and the body paragraph are fixed (only `{level}` is
substituted inside them). One template serves all levels, so the text-block
position is hardcoded in the view/CSS to fit this specific image — no per-level
layout and no drag-and-drop designer.

Typography: a serif face (title and name larger); the greeting/name line in the
template's red; completion date and level emphasised. Exact px positions and
sizes are determined against the real image during implementation.

## Components and behaviour

### 1. Issue flow (Admin only)

1. Admin opens **Issue Certificate**.
2. Selects a student (searchable). The student's **level** is pre-filled from
   their enrolment's course level (`Enrollment → Batch → Course.level`); it
   remains editable (a student may have multiple enrolments, or none).
3. The student's **name** is pre-filled and editable (to fix spelling/casing on
   the certificate).
4. Admin types the **father's name** (free text; no stored field — see data).
5. Admin picks the **completion date** (date input).
6. **Preview** renders the certificate; **Save & Print** stores the record and
   opens the print view.

### 2. Print view

- A dedicated Blade view, standalone layout (no app chrome), A4 landscape.
- `template.png` as the page background via `asset('storage/settings/template.png')`.
- The text block absolutely positioned over the empty area.
- `@media print` / `@page { size: A4 landscape; margin: 0 }` so the browser
  prints/saves edge-to-edge with no app furniture.

### 3. Issued record (light persistence)

A new **`student_certificates`** table (forward-only migration). A distinct name
is used so the unused legacy `certificates` table is left untouched and there is
no clash (see "Legacy tables"). One row per issued certificate, snapshotting the
text so later edits to the student never alter an issued document:

| Column              | Notes                                             |
|---------------------|---------------------------------------------------|
| `id`                | PK                                                |
| `student_id`        | FK → users, **nullable** on student delete (`nullOnDelete`) so issued history survives |
| `student_name`      | snapshot (as printed)                             |
| `father_name`       | snapshot                                          |
| `level`             | snapshot (e.g. `A1`)                              |
| `completion_date`   | date                                              |
| `certificate_number`| unique, human-readable (e.g. `LGL-A1-2026-001`)   |
| `issued_by`         | FK → users (the Admin)                            |
| `issued_at`         | timestamp                                         |
| timestamps          |                                                   |

- **Certificate number** is generated safely: a unique DB index on
  `certificate_number`, generated inside a transaction, retrying on collision
  (do **not** copy the legacy "read latest row" helper, which is not
  concurrency-safe). Format `LGL-{level}-{year}-{zero-padded sequence}`.
- The record enables **reprint** and a **"which students received a
  certificate"** list.
- An **AuditLog** row is written on issue (`certificate.issued`), consistent
  with existing audit actions.

### 4. UI placement

- Sidebar, admin-only **"Certificates"** entry: the issue form plus a list of
  issued certificates, each with a **Reprint** action.
- A **"Issue Certificate"** shortcut button on the student detail page that opens
  the issue form pre-selected to that student.

### 5. Access control

- Admin only, for issuing, listing, and reprinting. Routes guarded by the
  existing `role:admin` middleware. Teachers and students have no access in this
  iteration.

## Data flow

Issue: Admin submits student + father + level + date → controller creates a
`student_certificates` row (snapshot + generated number) → writes an audit row →
redirects to the print view.

Reprint: Admin opens an issued record → the same print view renders from the
stored snapshot (never re-derived from the live student).

## Error handling

- Missing `template.png`: the print view still renders the text block; it shows a
  plain background rather than erroring. (A note is logged.)
- Student with no enrolment: level is left blank for the Admin to type.
- Re-issuing to the same student is **allowed** (e.g. a corrected spelling or a
  second level); each issue is its own numbered record. No silent duplicate
  suppression in this iteration — kept simple per the owner's scope.

## Testing

- Issue: posting a valid student + father + level + date creates one
  `student_certificates` row with a unique number, an audit row, and redirects to
  the print view.
- Number uniqueness: two issues produce two distinct numbers; a forced collision
  retries rather than failing.
- Snapshot integrity: after issuing, changing the student's `name` does not
  change the stored `student_name` on the certificate.
- Print view: renders the student name, father name, level, and date, and
  references `storage/settings/template.png`.
- Access: a teacher and a student receive 403 on the issue/list/reprint routes;
  the student detail page shows the shortcut only to Admins.
- Level pre-fill: a student with an A1 enrolment pre-fills `A1`; a student with
  no enrolment leaves it blank.

## Legacy tables

The legacy `certificates` (2025-01-16) table and `Certificate` model, plus
`exams`/`exam_results`, are **out of scope** and left untouched. To avoid any
name clash and the unsafe legacy helpers, the new table is named
**`student_certificates`** (decided above). The legacy tables are never routed
and hold no production data, but dropping them is unnecessary for this feature,
so they are preserved as-is.

## Out of scope

- Any exam, result, or passing-rule logic.
- Signature upload / editable template fields / multi-template designer.
- Per-level body text (body is fixed; only `{level}` is substituted).
- Teacher or student access to certificates.
- New PDF/third-party packages (browser print only).
- Email/WhatsApp delivery of the certificate.
