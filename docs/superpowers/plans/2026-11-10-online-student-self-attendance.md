# Online Student Self-Attendance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a student in an online batch mark their own attendance for today's online session by clicking "Join Class" (present/late by time window), then forward them to the meeting link; Admin/Teacher keep full manual control and override.

**Architecture:** A new `AttendanceService::selfMarkOnline()` method (separate from the admin/teacher-only `markBulkAttendance`) validates an online session's time window and the student's enrollment, then writes one `attendances` row with `source='self_online'`. A single time-window helper on `ClassSession` is the one source of truth, used by both the service and the student dashboard button. Teacher override reuses the existing mark path unchanged.

**Tech Stack:** Laravel 11, Eloquent, Blade/Bootstrap, PHPUnit (SQLite `:memory:`), Pint.

---

## File Structure

- Modify `app/Models/ClassSession.php` — add `startAt()`, `endAt()`, `selfJoinState()` window helpers.
- Create `database/migrations/2026_11_10_000001_add_source_to_attendances_table.php` — additive `source` column.
- Modify `app/Models/Attendance.php` — add `source` to `$fillable`.
- Modify `app/Services/AttendanceService.php` — add `selfMarkOnline()`.
- Modify `app/Http/Controllers/AttendanceController.php` — add `joinOnline()`, exempt it from the admin/teacher gate.
- Modify `routes/web.php` — add the student POST route.
- Modify `app/Http/Controllers/StudentController.php` — eager-load `meeting_platform`/`status` on the dashboard session query.
- Modify `resources/views/dashboards/student.blade.php` — "Join Class" button + error flash.
- Create `tests/Feature/OnlineSelfAttendanceTest.php` — full coverage.

**Note on migrations:** this feature's `source` column is a NEW additive migration (AGENTS.md line 18 forbids rewriting applied migrations). The separate `users.cnic`/`users.father_name` edit to the original create-users migration was the owner's explicit instruction and is tracked outside this plan.

---

## Task 0: Branch and reservation

- [ ] **Step 1: Create a feature branch** (repo is on `main`)

Run:
```bash
git checkout -b feat/online-self-attendance
```

- [ ] **Step 2: Reserve the task in docs/TASKS.md**

Add under "Current reservation:" a line naming this task (A05 online student self-attendance), the files above, and "sole writer". Do not remove existing text.

- [ ] **Step 3: Commit the reservation**

```bash
git add docs/TASKS.md
git commit -m "chore: reserve A05 online student self-attendance"
```

---

## Task 1: ClassSession self-join window helpers

**Files:**
- Modify: `app/Models/ClassSession.php`
- Test: `tests/Feature/OnlineSelfAttendanceTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/OnlineSelfAttendanceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OnlineSelfAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:55:00', config('app.timezone')));
        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 30000, 'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->batch = Batch::create(['name' => 'A1 Online', 'course_id' => $course->id, 'teacher_id' => $teacher->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'ongoing', 'max_students' => 20, 'meeting_platform' => 'zoom', 'meeting_link' => 'https://zoom.us/j/123']);
        Enrollment::create(['student_id' => $this->student->id, 'batch_id' => $this->batch->id, 'enrollment_date' => '2026-10-01', 'total_fee' => 30000, 'status' => 'active']);
        $this->session = ClassSession::create(['batch_id' => $this->batch->id, 'session_date' => '2026-11-10', 'start_time' => '09:00', 'end_time' => '11:00', 'topic' => 'Greetings', 'status' => 'scheduled']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_window_state_present_late_and_closed(): void
    {
        // 08:55 is inside [08:45, 09:10] -> present
        $this->assertSame(['open' => true, 'status' => 'present'], collect($this->session->selfJoinState())->only(['open', 'status'])->all());

        Carbon::setTestNow(Carbon::parse('2026-11-10 09:30:00', config('app.timezone')));
        $this->assertSame('late', $this->session->selfJoinState()['status']);

        Carbon::setTestNow(Carbon::parse('2026-11-10 08:40:00', config('app.timezone')));
        $this->assertFalse($this->session->selfJoinState()['open']);

        Carbon::setTestNow(Carbon::parse('2026-11-10 11:05:00', config('app.timezone')));
        $this->assertFalse($this->session->selfJoinState()['open']);
    }
}
```

Property `$this->batch` is used in later tests; declare it with the others:
```php
    private Batch $batch;
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d extension=gd vendor/bin/phpunit --filter test_window_state_present_late_and_closed`
Expected: FAIL — `Call to undefined method App\Models\ClassSession::selfJoinState()`.

- [ ] **Step 3: Add the helpers to ClassSession**

In `app/Models/ClassSession.php`, add these methods (near the other helpers). Build the datetime from `session_date` + the RAW time string, matching the existing pattern at `teachingLabel()`:

```php
public function startAt(): \Illuminate\Support\Carbon
{
    return $this->session_date->copy()->setTimeFromTimeString($this->getRawOriginal('start_time'));
}

public function endAt(): \Illuminate\Support\Carbon
{
    return $this->session_date->copy()->setTimeFromTimeString($this->getRawOriginal('end_time'));
}

/**
 * Whether a student may self check-in right now, and as what status.
 * One source of truth for both the service and the dashboard button.
 *
 * @return array{open: bool, status: ?string, message: ?string}
 */
public function selfJoinState(?\Illuminate\Support\Carbon $now = null): array
{
    $now ??= now();
    if (! $this->isOnline()) {
        return ['open' => false, 'status' => null, 'message' => 'This session is not an online class.'];
    }
    if ($this->status === 'cancelled' || $this->batch->status === 'cancelled') {
        return ['open' => false, 'status' => null, 'message' => 'This class has been cancelled.'];
    }
    if (! $this->session_date->isSameDay($now)) {
        return ['open' => false, 'status' => null, 'message' => 'Self check-in is only available on the class day.'];
    }
    $start = $this->startAt();
    if ($now->lt($start->copy()->subMinutes(15))) {
        return ['open' => false, 'status' => null, 'message' => 'Join opens 15 minutes before the class.'];
    }
    if ($now->gt($this->endAt())) {
        return ['open' => false, 'status' => null, 'message' => 'This class has ended; your teacher will mark attendance.'];
    }

    return ['open' => true, 'status' => $now->lte($start->copy()->addMinutes(10)) ? 'present' : 'late', 'message' => null];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d extension=gd vendor/bin/phpunit --filter test_window_state_present_late_and_closed`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/ClassSession.php tests/Feature/OnlineSelfAttendanceTest.php
git commit -m "feat: add ClassSession self-join window helpers"
```

---

## Task 2: Additive `source` column on attendances

**Files:**
- Create: `database/migrations/2026_11_10_000001_add_source_to_attendances_table.php`
- Modify: `app/Models/Attendance.php`

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // null = manual / pre-existing; 'self_online' = student self check-in.
            $table->string('source')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
```

- [ ] **Step 2: Add `source` to the Attendance model fillable**

In `app/Models/Attendance.php`, change the `$fillable` array to include `'source'`:

```php
    protected $fillable = [
        'class_session_id',
        'student_id',
        'status',
        'source',
        'check_in_time',
        'notes',
    ];
```

- [ ] **Step 3: Run the migration against the test database to verify it applies**

Run: `php -d extension=gd vendor/bin/phpunit --filter test_window_state_present_late_and_closed`
Expected: PASS (RefreshDatabase runs the new migration; a red migration would error here).

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_11_10_000001_add_source_to_attendances_table.php app/Models/Attendance.php
git commit -m "feat: add additive source column to attendances"
```

---

## Task 3: `AttendanceService::selfMarkOnline()`

**Files:**
- Modify: `app/Services/AttendanceService.php`
- Test: `tests/Feature/OnlineSelfAttendanceTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `OnlineSelfAttendanceTest`:

```php
public function test_self_mark_present_persists_source_and_audit(): void
{
    $result = app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $this->student);

    $this->assertSame('present', $result['status']);
    $row = Attendance::where('class_session_id', $this->session->id)->where('student_id', $this->student->id)->sole();
    $this->assertSame('present', $row->status);
    $this->assertSame('self_online', $row->source);
    $this->assertNotNull($row->check_in_time);
    // Session is not completed by a self-mark.
    $this->assertSame('scheduled', $this->session->fresh()->status);
    $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.self_marked', 'actor_id' => $this->student->id]);
}

public function test_self_mark_late_after_grace(): void
{
    Carbon::setTestNow(Carbon::parse('2026-11-10 09:30:00', config('app.timezone')));
    $result = app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $this->student);
    $this->assertSame('late', $result['status']);
}

public function test_self_mark_blocked_before_window(): void
{
    Carbon::setTestNow(Carbon::parse('2026-11-10 08:40:00', config('app.timezone')));
    $this->expectException(\Illuminate\Validation\ValidationException::class);
    app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $this->student);
}

public function test_self_mark_blocked_for_non_online_batch(): void
{
    $this->batch->update(['meeting_platform' => 'in_person']);
    $this->expectException(\Illuminate\Validation\ValidationException::class);
    app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session->fresh(), $this->student);
}

public function test_self_mark_blocked_for_non_enrolled_student(): void
{
    $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
    $this->expectException(\Illuminate\Validation\ValidationException::class);
    app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $other);
}

public function test_self_mark_is_idempotent_and_never_overwrites(): void
{
    // Teacher already marked the student absent.
    Attendance::create(['class_session_id' => $this->session->id, 'student_id' => $this->student->id, 'status' => 'absent']);
    $result = app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $this->student);

    $this->assertTrue($result['already']);
    $this->assertSame(1, Attendance::where('class_session_id', $this->session->id)->where('student_id', $this->student->id)->count());
    $this->assertSame('absent', Attendance::where('class_session_id', $this->session->id)->where('student_id', $this->student->id)->sole()->status);
}

public function test_self_mark_forbidden_for_inactive_student(): void
{
    $this->student->update(['is_active' => false]);
    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $this->student->fresh());
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php -d extension=gd vendor/bin/phpunit --filter OnlineSelfAttendanceTest`
Expected: the six new tests FAIL — `Call to undefined method ...::selfMarkOnline()`.

- [ ] **Step 3: Implement `selfMarkOnline`**

In `app/Services/AttendanceService.php`, add `use App\Models\User;` at the top (alongside the existing `use` lines) and add this method to the class:

```php
/**
 * A student marks their own attendance for today's online session.
 * Separate from markBulkAttendance (which is admin/teacher only).
 *
 * @return array{status: string, already: bool}
 */
public function selfMarkOnline(ClassSession $session, User $student): array
{
    abort_unless($student->is_active && $student->role === 'student', 403);

    return \Illuminate\Support\Facades\DB::transaction(function () use ($session, $student) {
        $session = ClassSession::lockForUpdate()->findOrFail($session->id);

        $state = $session->selfJoinState();
        if (! $state['open']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['join' => $state['message']]);
        }

        $eligible = $session->eligibleEnrollments()->where('student_id', $student->id)->exists();
        if (! $eligible) {
            throw \Illuminate\Validation\ValidationException::withMessages(['join' => "You are not enrolled in this session's batch."]);
        }

        $existing = Attendance::where('class_session_id', $session->id)->where('student_id', $student->id)->first();
        if ($existing) {
            return ['status' => $existing->status, 'already' => true];
        }

        $record = Attendance::create([
            'class_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => $state['status'],
            'source' => 'self_online',
            'check_in_time' => now()->format('H:i:s'),
        ]);

        \App\Models\AuditLog::create([
            'actor_id' => $student->id,
            'action' => 'attendance.self_marked',
            'subject_type' => 'attendance',
            'subject_id' => $record->id,
            'reason' => null,
            'changes' => ['after' => ['status' => $record->status, 'source' => 'self_online']],
            'created_at' => now(),
        ]);

        return ['status' => $record->status, 'already' => false];
    });
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php -d extension=gd vendor/bin/phpunit --filter OnlineSelfAttendanceTest`
Expected: PASS (all tests so far).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AttendanceService.php tests/Feature/OnlineSelfAttendanceTest.php
git commit -m "feat: add AttendanceService::selfMarkOnline"
```

---

## Task 4: Route + controller `joinOnline`

**Files:**
- Modify: `app/Http/Controllers/AttendanceController.php`
- Modify: `routes/web.php:285-294` (student route group)
- Test: `tests/Feature/OnlineSelfAttendanceTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `OnlineSelfAttendanceTest`:

```php
public function test_join_route_marks_present_and_redirects_to_meeting(): void
{
    $response = $this->actingAs($this->student)->post(route('student.session.join', $this->session));

    $response->assertRedirect('https://zoom.us/j/123');
    $this->assertDatabaseHas('attendances', ['class_session_id' => $this->session->id, 'student_id' => $this->student->id, 'status' => 'present', 'source' => 'self_online']);
}

public function test_join_route_out_of_window_redirects_back_with_error_and_no_record(): void
{
    Carbon::setTestNow(Carbon::parse('2026-11-10 08:40:00', config('app.timezone')));
    $response = $this->actingAs($this->student)->from(route('student.dashboard'))->post(route('student.session.join', $this->session));

    $response->assertRedirect(route('student.dashboard'));
    $response->assertSessionHas('error');
    $this->assertDatabaseMissing('attendances', ['class_session_id' => $this->session->id, 'student_id' => $this->student->id]);
}

public function test_join_route_rejects_teacher_role(): void
{
    $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
    $this->actingAs($teacher)->post(route('student.session.join', $this->session))->assertForbidden();
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php -d extension=gd vendor/bin/phpunit --filter OnlineSelfAttendanceTest`
Expected: the three new tests FAIL — route `student.session.join` not defined.

- [ ] **Step 3: Add the controller method and exempt it from the staff gate**

In `app/Http/Controllers/AttendanceController.php`, update the constructor's role line to exempt `joinOnline` (so the student route group's `role:student` governs it):

```php
        $this->middleware('role:admin,teacher')->except(['myAttendance', 'studentAttendance', 'joinOnline']);
```

Add the method:

```php
public function joinOnline(Request $request, ClassSession $session)
{
    try {
        $this->attendanceService->selfMarkOnline($session, $request->user());
    } catch (\Illuminate\Validation\ValidationException $e) {
        return back()->with('error', collect($e->errors())->flatten()->first());
    }

    $link = $session->meeting_link ?: $session->batch->meeting_link;
    if (! $link) {
        return back()->with('error', 'No meeting link is set for this class.');
    }

    return redirect()->away($link);
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, inside the `Route::middleware('role:student')->group(...)` block (around line 285-294), add:

```php
        Route::post('/student/sessions/{session}/join', [AttendanceController::class, 'joinOnline'])
            ->name('student.session.join');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php -d extension=gd vendor/bin/phpunit --filter OnlineSelfAttendanceTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AttendanceController.php routes/web.php tests/Feature/OnlineSelfAttendanceTest.php
git commit -m "feat: add student join-online self-attendance route"
```

---

## Task 5: Teacher override regression test

**Files:**
- Test: `tests/Feature/OnlineSelfAttendanceTest.php`

- [ ] **Step 1: Write the test** (no production change — proves the existing path still overrides a self row)

Append to `OnlineSelfAttendanceTest`:

```php
public function test_teacher_can_override_a_self_marked_row(): void
{
    app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $this->student);
    $teacher = $this->session->batch->teacher;

    $this->actingAs($teacher);
    app(\App\Services\AttendanceService::class)->markBulkAttendance(
        $this->session->id,
        [['student_id' => $this->student->id, 'status' => 'absent']],
        'Student was not actually present in the meeting.'
    );

    $row = Attendance::where('class_session_id', $this->session->id)->where('student_id', $this->student->id)->sole();
    $this->assertSame('absent', $row->status);
    $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.corrected']);
}
```

- [ ] **Step 2: Run the test**

Run: `php -d extension=gd vendor/bin/phpunit --filter test_teacher_can_override_a_self_marked_row`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/OnlineSelfAttendanceTest.php
git commit -m "test: teacher override of self-marked attendance"
```

---

## Task 6: Student dashboard "Join Class" button

**Files:**
- Modify: `app/Http/Controllers/StudentController.php` (dashboard session query ~line 219)
- Modify: `resources/views/dashboards/student.blade.php`

- [ ] **Step 1: Eager-load the fields the button needs**

In `app/Http/Controllers/StudentController.php`, the dashboard upcoming-sessions query currently loads `->with('batch:id,name,meeting_link')`. Change that `with` to include the platform and status so `isOnline()`/cancelled checks work without extra queries:

```php
            ->with('batch:id,name,meeting_link,meeting_platform,status')
```

(Leave the rest of the query — `where('status','scheduled')`, `whereDate('session_date','>=',today())`, ordering, limit — unchanged.)

- [ ] **Step 2: Replace the plain link with the Join Class button**

In `resources/views/dashboards/student.blade.php`, replace the Upcoming Sessions `<li>` body (the line with "Join meeting") with:

```blade
        <li class="list-group-item">{{ $session->batch->name }} — {{ $session->topic }}, {{ $session->session_date->format('d M Y') }} {{ $session->start_time->format('h:i A') }}
            @php($state = $session->selfJoinState())
            @if($state['open'])
                <form method="POST" action="{{ route('student.session.join', $session) }}" class="tw:inline-block tw:ml-2">@csrf
                    <button type="submit" class="btn btn-sm btn-primary">Join Class</button>
                </form>
            @elseif($session->meeting_link ?? $session->batch->meeting_link)
                <a href="{{ $session->meeting_link ?? $session->batch->meeting_link }}" rel="noopener noreferrer">Join meeting</a>
                @if($session->isOnline() && $state['message'])<small class="text-muted tw:ml-2">{{ $state['message'] }}</small>@endif
            @endif
        </li>
```

- [ ] **Step 3: Show the error flash near the top of the dashboard**

In `resources/views/dashboards/student.blade.php`, immediately after `@section('content')`, add:

```blade
@if(session('error'))<div class="alert alert-warning">{{ session('error') }}</div>@endif
```

- [ ] **Step 4: Verify the view renders with a button for an in-window online session**

Append to `OnlineSelfAttendanceTest`:

```php
public function test_dashboard_shows_join_class_button_for_online_session_in_window(): void
{
    $response = $this->actingAs($this->student)->get(route('student.dashboard'));
    $response->assertOk();
    $response->assertSee(route('student.session.join', $this->session), false);
    $response->assertSee('Join Class');
}
```

Run: `php -d extension=gd vendor/bin/phpunit --filter test_dashboard_shows_join_class_button_for_online_session_in_window`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/StudentController.php resources/views/dashboards/student.blade.php tests/Feature/OnlineSelfAttendanceTest.php
git commit -m "feat: student dashboard Join Class button"
```

---

## Task 7: Full verification

**Files:** none (verification only)

- [ ] **Step 1: Run the new suite**

Run: `php -d extension=gd vendor/bin/phpunit --filter OnlineSelfAttendanceTest`
Expected: all tests PASS.

- [ ] **Step 2: Run the existing attendance suites for regressions**

Run: `php -d extension=gd vendor/bin/phpunit --filter 'BiometricAttendanceTest|BatchAndSessionTest|ListPagesTest'`
Expected: PASS (pre-existing unrelated failures noted in docs/TASKS.md, if any, are not caused by this change — confirm by stashing).

- [ ] **Step 3: Lint**

Run: `vendor/bin/pint app/Models/ClassSession.php app/Models/Attendance.php app/Services/AttendanceService.php app/Http/Controllers/AttendanceController.php app/Http/Controllers/StudentController.php`
Then: `git diff --check`
Expected: Pint passes; no whitespace errors.

- [ ] **Step 4: Record evidence in docs/TASKS.md and release the reservation**

Add an A05 completion entry (behavior, exact test counts, limitations: no browser/real-meeting check, active DB untouched, self-review only) and set the reservation back to none.

- [ ] **Step 5: Commit**

```bash
git add docs/TASKS.md
git commit -m "docs: record A05 online self-attendance evidence"
```

---

## Verification notes

- All DB-writing verification runs in forced isolated SQLite `:memory:` (the project's phpunit config). The additive `source` migration applies only there.
- No writes to the active `lgl_system` database. Deployment needs `php artisan migrate` to add `attendances.source`.
- Self-review only unless an independent review is explicitly requested.
