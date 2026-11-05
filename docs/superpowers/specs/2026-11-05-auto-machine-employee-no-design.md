# Fully automatic Machine Employee No. — design

Date: 2026-11-05
Status: Approved (brainstorming), pending implementation plan

## Problem

The person forms (Add/Edit Staff, Add/Edit Student/Teacher) expose a manual
**Machine Employee No.** text input. Admins read this as "I must type a number,"
but mapping to the attendance machine is already automatic: on user create/update
the `User` model events call `MachineUserSync::userChanged`, which assigns a number
via `BiometricCode::ensure` (when empty) and queues an upsert to every active
`BiometricDevice`. The input is only an optional override.

The owner wants the number to be fully automatic — no manual field — and wants the
form to show the assigned number plus the per-machine sync state, with a button to
re-send the person to the machines.

## Decision

Remove the manual input. The number is always system-generated. On the person's
edit form, show the assigned number read-only, a per-machine sync status list, and a
"Sync now" button.

### Accepted tradeoffs

- No manual override: an admin can no longer type a number to match a person already
  enrolled on a machine with a specific Employee No. The number is always generated.
- The number is always numeric. The old override regex allowed letters (`[A-Za-z0-9]`);
  `BiometricCode` only ever produces digits.
- Admin-role users still receive no number (they do not punch) — unchanged.

## Scope

**Views (remove input, add read-only block):**
- `resources/views/staff/form.blade.php` (field at lines ~47–51)
- `resources/views/admin/people/form.blade.php` (field at lines ~37–41)
- `resources/views/admin/students/edit.blade.php` (confirm with grep; remove if a
  standalone input exists there)

**Controllers (drop `device_employee_no` validation + handling, store + update):**
- `app/Http/Controllers/StaffController.php`
- `app/Http/Controllers/TeacherController.php`
- `app/Http/Controllers/StudentController.php`

**Service / routes:**
- `app/Biometric/MachineUserSync.php` — add `resyncUser(User)`.
- Routes — add admin-only `POST /people/{user}/resync-machine` (final path/name
  decided in the plan, matching existing route conventions).

**Tests:**
- Update `tests/Feature/StaffTest.php` and any biometric test that posts
  `device_employee_no`.
- Add coverage for `resyncUser` and the read-only display.

## Components and behavior

### 1. Read-only display block (edit only)

When the person exists and has a `device_employee_no`:
- Show `Machine Employee No.: <number>` as read-only text (not an input).
- Per active `BiometricDevice`, show one row with this person's state read from
  `biometric_device_users` (matched on `user_id` + `biometric_device_id`):
  - `synced` → "Synced"
  - `pending` → "Waiting"
  - `failed` → "Failed" + `last_error`
  - no row → "Not queued"

On create (no number yet): show a note, "Assigned automatically on save," and no list.

Data source: `BiometricDeviceUser` rows for the user, plus the active device list.
Load eagerly to avoid N+1.

### 2. Sync-now button

- Rendered as its own small form/button, placed outside the main person form
  (HTML forms cannot nest).
- Submits to an admin-only `POST` route that calls `MachineUserSync::resyncUser($user)`
  and redirects back with a success flash.
- Only shown on edit, when the person is eligible (active student/teacher/staff) and
  at least one active machine exists.

### 3. `MachineUserSync::resyncUser(User $user)`

Re-send one person to all active machines (the per-user analogue of the existing
per-machine `resync`/`queueAll`). Behavior:
- If eligible, call `BiometricCode::ensure($user)` so the number exists.
- Mark this user's existing `biometric_device_users` rows pending, reset
  `attempts`/`last_error`, bump `version` (same reset the controller's `resync` does
  per machine).
- For each active device, queue `upsert` when eligible, `delete` when not, via the
  existing private `queue()` path (extract/reuse as needed).
- Wrap in a DB transaction; respect the existing `ready()` table-exists guard.
- Caller (controller) writes an audit row (`biometric_device_user.resync` or similar),
  consistent with existing biometric audit actions.

### 4. Controller cleanup

In each of the three controllers' store + update:
- Remove the `device_employee_no` validation rule.
- Remove the `$machineNo` extraction and the `forceFill(['device_employee_no' => ...])`.
- The number is assigned solely by the `User` events → `MachineUserSync` → `BiometricCode`.

This leaves the create/update path backward-safe: a request that still carries
`device_employee_no` is simply ignored.

## Data flow

Create: controller saves user (no number) → `created` event → `MachineUserSync`
assigns number + queues upserts → bridge PC applies on next run.

Edit display: form loads user with its `biometric_device_users` (+ active devices) →
renders read-only number and per-machine status.

Sync now: admin clicks button → route → `resyncUser` re-queues pending → audit →
redirect with flash → bridge PC applies on next run.

## Error handling

- `resyncUser` is a no-op (guarded) when the `biometric_device_users` table is absent
  (`ready()` check), mirroring `userChanged`.
- Admin-role / ineligible users: button hidden; if somehow posted, `resyncUser` queues
  deletes only and assigns no number.
- Unique-number collisions remain handled inside `BiometricCode::ensure`.

## Testing

- `resyncUser`: active student/teacher/staff gets a number and pending upsert rows for
  each active machine; ineligible/inactive user gets delete rows and no number;
  no-op when the table is absent.
- Read-only display: edit form renders the number and correct per-machine status
  labels; create form shows the "assigned on save" note and no input.
- Controller cleanup: posting a user with no `device_employee_no` still results in an
  auto number; a posted `device_employee_no` is ignored (no override).
- Update existing tests that relied on the manual field.

## Out of scope

- Removing the `users.device_employee_no` column or its unique index (kept; still the
  machine Employee No.).
- Changing the bridge PC script, the push hook, or `ClassPunchRecorder`.
- Any change to the Admin > Attendance Machine page (its existing per-machine resync
  stays).
