# Online Student Self-Attendance — Design

Date: 2026-11-10
Status: Approved (owner, chat)
Author: Claude Code

## Problem

Students in online batches (Zoom / Google Meet) have no way to record their own
attendance. Today all attendance is marked by Admin/Teacher through
`AttendanceService::markBulkAttendance` (role-gated to admin/teacher). Physical
batches get automatic attendance from the Hikvision biometric push; online
batches have no equivalent, so a teacher must manually mark every online
student. There is no student-facing self-mark path at all.

## Goal

Let an enrolled student in an **online** batch mark their own attendance for
**today's** online session by clicking a "Join Class" button, which marks them
present (or late) and then forwards them to the meeting link. Admin/Teacher keep
full manual control and can override any self-mark.

## Approach

Chosen: **Join = auto present**, inside a time window, with teacher override.

Alternatives considered and rejected:
- **Separate "Mark present" button** (join link + independent mark): lets a
  student mark present without joining; more friction, less trustworthy.
- **Real Zoom / Google Meet API join verification**: most accurate but needs
  per-platform OAuth + API integration. Deferred to a possible phase 2.

## Scope

In scope:
- One self-mark action for a student on an online session scheduled today.
- Present vs late by time window.
- Teacher/Admin override of self-marks (via the existing mark screen; no change
  needed there).

Out of scope (YAGNI):
- Auto-absent scheduled job for students who never join (AGENTS.md keeps
  automatic absence deferred/guarded).
- Real meeting-platform join verification (API).
- Geolocation / IP proof-of-presence.

## Definitions

- **Online session**: `ClassSession::isOnline()`, which delegates to
  `Batch::isOnline()` = `meeting_platform ∈ {zoom, google_meet}`.
- **Eligible student**: present in `ClassSession::eligibleEnrollments()` for the
  session (enrolled on/before `session_date`, active/completed or
  transferred/dropped after the session date), role = student.
- **Window** (relative to `session_date` + `start_time`/`end_time`, Asia/Karachi):
  - Join button active from `start_time − 15 min` to `end_time`.
  - `now ≤ start_time + 10 min` → **present**; after that until `end_time` → **late**.
  - Before open or after `end_time` → no self-mark (teacher marks manually).

## Components

### 1. Migration (additive)

New file `database/migrations/2026_11_10_000001_add_source_to_attendances_table.php`.
- Adds `attendances.source` — string, nullable, default `null`.
  - `null` = manual / pre-existing (back-compatible with all current rows).
  - `'self_online'` = marked by the student via Join Class.
- `down()` drops `source`.

Rationale for additive (vs editing the original attendances migration): this is
a new feature column, and AGENTS.md line 18 forbids rewriting applied
migrations. (The separate `users.cnic` / `users.father_name` change was an
explicit owner instruction to edit the original create-users migration and is
tracked outside this feature.)

Reuse existing `attendances.check_in_time` (time, nullable) for the join time.

### 2. Model

`App\Models\Attendance`: add `source` to `$fillable` (and confirm
`check_in_time` is writable).

### 3. Service — `AttendanceService::selfMarkOnline(ClassSession $session, User $student): array`

Separate from `markBulkAttendance` (which stays admin/teacher-only). Runs in a
DB transaction with a row lock on the session. Order of checks:

1. `abort_unless($student->is_active && $student->role === 'student', 403)`.
2. `$session->isOnline()` must be true — else a validation error
   ("This session is not an online class.").
3. Session not cancelled and batch not cancelled.
4. `$student` is in `$session->eligibleEnrollments()` — else error
   ("You are not enrolled in this session's batch.").
5. `session_date == today` — else error ("Self check-in is only available on the
   class day.").
6. Time window: `now ∈ [start − 15 min, end]`.
   - Before open → "Join opens 15 minutes before the class."
   - After `end_time` → "This class has ended; your teacher will mark attendance."
7. Status: `now ≤ start + 10 min` ? `present` : `late`.
8. If an attendance row already exists for `(session, student)` → **do not
   overwrite** (idempotent). Return an "already recorded" result. This protects a
   teacher's prior mark and makes repeat clicks safe.
9. Otherwise create `Attendance`: `status`, `check_in_time = now time`,
   `source = 'self_online'`.
10. Write `AuditLog`: `actor_id = student->id`, `action = 'attendance.self_marked'`,
    subject = the attendance row.
11. **Do not** change `session.status` (session stays `scheduled`/`ongoing`;
    only teacher/admin bulk marking completes a session).

Returns an array describing the outcome (`marked` status, or `already`, with a
message) so the controller can redirect or flash accordingly.

### 4. Controller + route

- Route: `POST /student/sessions/{session}/join`, name `student.session.join`,
  middleware `auth` + `role:student`.
- `AttendanceController::joinOnline(ClassSession $session)` (student-only):
  - Calls `selfMarkOnline`.
  - On a successful or already-recorded self-mark → `redirect()->away($link)`
    where `$link = $session->meeting_link ?? $session->batch->meeting_link`.
  - On a validation failure → `back()->with('error', <message>)`.
- POST (not GET) so browser/link prefetch cannot double-mark.

### 5. Views

- Student dashboard (`dashboards.student`) and student timetable: for today's
  online sessions, replace the raw meeting link with a "Join Class" POST button.
  Outside the window, render it disabled with the reason text.
- Student attendance page: optional small "self" badge where `source = 'self_online'`.
- No teacher-side view changes.

### 6. Teacher override

No code change. The existing `markBulkAttendance` path already updates an
existing attendance row when a reason (5–1000 chars) is supplied and writes an
`attendance.corrected` audit row. A teacher can change a `self_online` row to
any status there.

## Data flow

1. Student opens dashboard → sees "Join Class" for an online session in window.
2. Student submits the POST form.
3. `joinOnline` → `selfMarkOnline` validates window + eligibility, creates the
   attendance row (`present`/`late`, `source='self_online'`, `check_in_time`),
   audits it.
4. Controller redirects the student to the meeting link.
5. Teacher later opens the mark screen, marks the students who never joined, and
   can override any self-mark (with a reason).

## Error handling

All failure cases raise a `ValidationException` (or `abort(403)` for
role/active) with a specific message; the controller flashes it via
`back()->with('error', ...)`. No partial writes (single transaction). Repeat
clicks are idempotent (step 8). Future/past days are rejected (step 5).

## Testing

New `tests/Feature/OnlineSelfAttendanceTest.php`:
- Present when clicked within `start + 10 min`.
- Late when clicked after `start + 10 min` but before `end_time`.
- Blocked before `start − 15 min` (not open yet).
- Blocked after `end_time` (closed).
- Blocked for a non-online batch.
- Blocked for a student not enrolled in the batch.
- Idempotent: a second click does not duplicate and does not override an
  existing (including teacher-set) status.
- Teacher override of a `self_online` row still works via `markBulkAttendance`
  (with reason) and audits as `attendance.corrected`.
- A student cannot self-mark another student (route is bound to the auth user).
- Inactive student blocked.
- `source = 'self_online'` and `check_in_time` are persisted.

## Verification plan

- `php -d extension=gd vendor/bin/phpunit --filter OnlineSelfAttendance` (forced
  isolated SQLite `:memory:`), plus the existing attendance suites to confirm no
  regression.
- Pint on changed PHP files; `git diff --check`.
- No writes to the active `lgl_system` database. The additive `source` migration
  is applied only in the isolated test database.
- Self-review only unless an independent review is explicitly run.

## Deployment notes

- Run `php artisan migrate` to add `attendances.source` (one additive column).
- The `users.cnic` / `users.father_name` columns were added by editing the
  original create-users migration (owner's explicit choice); they reach the live
  database only on a fresh migrate, not on the existing `lgl_system`. If they are
  needed on the live DB without a reset, add them there separately (additive
  migration or manual `ALTER`). Tracked outside this feature.
