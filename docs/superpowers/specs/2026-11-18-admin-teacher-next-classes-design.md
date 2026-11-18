# Admin dashboard: teachers' next classes — design

Date: 18 November 2026. Status: owner-approved design (chat).

## Goal

Admin sees, on the admin dashboard, each teacher's next class (today, tomorrow or later this week), derived from batch timetables, so the admin knows who teaches what next.

## Layout (owner choice: teacher cards)

New section **"Teachers' next classes"** on `dashboards/admin.blade.php`, below the stats row and batch reminders. Responsive grid of cards (1 column mobile, 3–4 desktop), reusing existing `panel` / `status-pill` styles.

Each card:
- Teacher name.
- **NEXT** block: day label (`Today`, `Tomorrow`, or `Thu 19 Nov`), time range (`4:00–5:30 PM`), level + batch code linking to the batch show page, relative label (`in 45 min`, `in 2 h`, or the day label for later days).
- **LIVE** pill instead of the relative label when the slot is running now.
- **Later today**: up to 2 further slots for the same teacher today.
- **On leave · until {date}** badge when the teacher has approved leave covering today.
- Teachers with no class in the 7-day window: "No class in next 7 days".

Card order: LIVE first, then soonest next class, then teachers with none.

## Data rules

Window: now → end of day 7 days ahead, app timezone, computed on page load (no live ticking).

Included teachers: active teachers with at least one `ongoing` batch.

Slots per date in the window:
1. **Timetable**: each `batch_schedules` row whose `weekday` matches the date, for `ongoing` batches whose `start_date <= date` and (`end_date` null or `>= date`).
2. **Cancelled**: a `class_sessions` row for the same batch, same date and same start time with `status = cancelled` removes that timetable slot.
3. **Extra/makeup**: `class_sessions` rows in the window that are not cancelled and do not match a timetable slot (same batch, date, start time) are added as slots.
4. **Leave**: dates covered by the teacher's approved `teacher_leaves` (`start_date..end_date`) have all their slots removed.
5. Today's slots whose end time has passed are dropped; a slot with start ≤ now < end is LIVE.

## Components

- `app/Services/TeacherScheduleService.php` (new): `upcomingForTeachers(int $days = 7): Collection` (uses `Carbon::now()`, frozen in tests) returning per teacher `{teacher, next, laterToday, onLeaveUntil}`, where each slot is `{batch, date, start, end, live, source}`. Pure read; no writes.
- `AdminController::dashboard` (the web `/admin/dashboard` page; `DashboardController::adminDashboard` is a JSON endpoint and is not changed): call the service and pass `$teacherNextClasses` to the view.
- `resources/views/dashboards/partials/teacher-next-classes.blade.php` (new): renders the cards; included from `admin.blade.php`.

## Access

Admin only, through the existing `adminDashboard` authorization. No new routes.

## Tests

`tests/Feature/AdminTeacherNextClassTest.php` on isolated SQLite, with frozen time:
- upcoming slot today shows as NEXT with "in N min";
- a slot already ended today moves NEXT to the following scheduled day;
- running slot shows LIVE;
- cancelled session removes the slot;
- makeup session appears as a slot;
- approved leave shows the badge and pushes NEXT past the leave;
- batch outside its start/end dates or not `ongoing` is excluded;
- teacher with no slots shows "No class in next 7 days";
- non-admin cannot see the section (403 / not rendered).

## Out of scope

Live countdown/AJAX refresh, weekly grid view, teacher-facing changes, notifications.
