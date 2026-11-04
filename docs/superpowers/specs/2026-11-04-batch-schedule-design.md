# Batch weekly schedule + teacher hours + student timetable

Date: 2026-11-04. Owner-requested. Status: implemented locally (V02).

## Problem

A batch's class days and times lived only in the batch **name** (free text) plus the
concrete `class_sessions` generated from it (F14 decision). There was no structured
"this batch runs Mon/Wed 18:00–20:00", teachers had no weekly-hours figure, and students
had no single timetable page. Owner asked to add all three.

## Decisions

- Structured schedule stored in a new `batch_schedules` table (one row per class day),
  supporting a **different time per day**. Schedule is a **template**: editing it does not
  rewrite already-generated sessions.
- Teacher weekly hours = **planned**, summed from the schedule (`Σ end−start`), per batch
  and total. Shown even before sessions are generated.
- Students get a dedicated **My Timetable** page (sidebar) with each active batch's days,
  times, platform and Join link, plus upcoming concrete sessions.
- Schedule is **optional** (a batch can have none). Admin edits it on the batch form;
  Teacher/Student are read-only.

## Data model

`batch_schedules`: `id, batch_id (FK cascade), weekday (mon…sun enum), start_time (time),
end_time (time), timestamps`, index `(batch_id, weekday)`. Migration
`2026_11_04_000001_create_batch_schedules_table`; additive and droppable (academic, not
ledger). `Batch hasMany schedules`; helpers `orderedSchedules()`, `weeklyMinutes()`,
`weeklyHours()`. `BatchSchedule::minutes()`, `weekdayLabel()`, `WEEKDAYS` constant.

## Behaviour

1. **Batch form (Admin)** — "Class schedule" section with repeatable `[Day][Start][End][×]`
   rows (inline vanilla JS add/remove; a `<template>` clones new rows). Blank rows are
   dropped; an incomplete row or `end ≤ start` is rejected with a `schedule` error.
   `BatchController::cleanSchedule()` + `saveSchedule()` (delete-and-reinsert) run in
   `store` and inside the existing `update` transaction.
2. **Batch show** — weekly-schedule list + `weeklyHours()` badge; a "Generate from weekly
   schedule" form (from/to, defaults to batch dates) alongside the existing manual generator.
3. **Generate from schedule** — `ClassSessionController::generateFromSchedule()`
   (POST `/batches/{batch}/sessions/generate-from-schedule`, Admin or the batch teacher).
   Creates one session per scheduled weekday in range, **each at that day's own time**,
   clamped to batch dates and today-onward, skipping any date that already has a session at
   the same start time (idempotent; supports multiple slots per day), capped at 200, one
   audit row `class_sessions.generated_from_schedule`. Copies the batch meeting link.
4. **Teacher dashboard** — KPI "Teaching load N h / week (planned from batch schedules)";
   each assigned batch shows its days/times and an `h/wk` badge.
5. **Student My Timetable** — `StudentController::timetable()` (GET `/student/timetable`,
   role:student), self-scoped: active-enrollment batches' schedule + Join link, plus next
   20 scheduled sessions with Join buttons. New sidebar item.

## Invariants / access

- Times are wall-clock in the app timezone (Asia/Karachi), consistent with sessions.
- Editing/removing a schedule never deletes or alters existing sessions.
- Schedule edit is Admin-only (batch create/update already is); Teacher/Student read-only,
  and a teacher cannot generate for a batch that is not theirs (403). Student timetable is
  self-scoped — another student sees nothing of a batch they are not in.

## Tests

`tests/Feature/BatchScheduleTest.php` (10): create with schedule + weekly-hours math, edit
replaces schedule, blank-row drop + incomplete-row reject, end-after-start, generate-from-
schedule per-day times + idempotent rerun, generate requires a schedule, generate blocked
for another teacher, teacher dashboard hours, student timetable self-scoping + Join link,
teacher cannot create a batch. Full suite 130 passed (1541 assertions).
