# Fully automatic Machine Employee No. — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the manual Machine Employee No. input so the number is always system-generated, and show each person's assigned number plus per-machine sync status and a "Sync now" button on their edit form.

**Architecture:** The number is already auto-assigned by `User` model events → `MachineUserSync::userChanged` → `BiometricCode::ensure`. We delete the override input from the two person forms, drop the field from the three controllers, add a per-user re-sync service method + admin route, and render a read-only status partial on the forms.

**Tech Stack:** Laravel 12, PHP 8.2, Blade/Bootstrap, PHPUnit with SQLite :memory:.

**Branch:** `feature/auto-machine-employee-no` (already checked out).

---

## Key facts (verified)

- `User::booted()` wires `created`/`updated` → `MachineUserSync::userChanged` (`app/Models/User.php:32-33`). Auto-assignment already works; removing the input does not break it.
- Students **and** teachers both render `resources/views/admin/people/form.blade.php` (StudentController & TeacherController `create`/`edit`). Staff render `resources/views/staff/form.blade.php`. So **two** form views carry the input.
- `resources/views/admin/students/edit.blade.php` is **not** rendered by any controller action and already shows a read-only badge — leave it untouched.
- `MachineUserSync` has a private `queue(device, employeeNo, user, action)`, `eligible(user)`, and `ready()`; `BiometricCode` is injected. Eligible roles: `student`, `teacher`, `staff`.
- `BiometricDeviceUser` columns: `biometric_device_id`, `employee_no`, `user_id`, `name`, `action`, `status` (pending/synced/failed), `attempts`, `last_error`, `version`.

## File structure

- Modify `app/Biometric/MachineUserSync.php` — add public `resyncUser(User)`.
- Modify `routes/web.php` — add admin route `biometric.resync-user`.
- Modify `app/Http/Controllers/BiometricController.php` — add `resyncUser()` action.
- Create `resources/views/partials/machine-sync.blade.php` — read-only number, per-machine status, Sync-now button.
- Modify `resources/views/admin/people/form.blade.php` — remove input, include partial.
- Modify `resources/views/staff/form.blade.php` — remove input, include partial.
- Modify `app/Http/Controllers/StaffController.php`, `TeacherController.php`, `StudentController.php` — drop `device_employee_no` validation + handling.
- Modify `tests/Feature/BiometricSyncTest.php` — add `resyncUser` + route tests; update the override assertions.

---

### Task 1: `MachineUserSync::resyncUser()`

**Files:**
- Modify: `app/Biometric/MachineUserSync.php`
- Test: `tests/Feature/BiometricSyncTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/BiometricSyncTest.php`:

```php
    public function test_resync_user_requeues_one_person_to_all_active_machines(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Re Sync']);
        $device = $this->device();
        $code = $student->fresh()->device_employee_no;
        BiometricDeviceUser::query()->update(['status' => 'synced', 'attempts' => 2, 'last_error' => 'old']);

        app(\App\Biometric\MachineUserSync::class)->resyncUser($student->fresh());

        $row = BiometricDeviceUser::where('user_id', $student->id)->sole();
        $this->assertSame([$code, 'upsert', 'pending', 0, null], [$row->employee_no, $row->action, $row->status, $row->attempts, $row->last_error]);
        $this->assertSame(2, $row->version); // bumped from 1

        // Ineligible user: rows become deletes, no number assigned.
        $student->update(['is_active' => false]);
        BiometricDeviceUser::query()->update(['status' => 'synced']);
        app(\App\Biometric\MachineUserSync::class)->resyncUser($student->fresh());
        $this->assertSame('delete', BiometricDeviceUser::where('user_id', $student->id)->sole()->action);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter test_resync_user_requeues_one_person_to_all_active_machines`
Expected: FAIL with `Call to undefined method App\Biometric\MachineUserSync::resyncUser()`.

- [ ] **Step 3: Implement `resyncUser`**

In `app/Biometric/MachineUserSync.php`, add after `queueAll()` (keep `use` of `BiometricDevice`, `BiometricDeviceUser`, `DB` already present):

```php
    /** Re-send one person to every active machine (per-user analogue of queueAll). */
    public function resyncUser(User $user): void
    {
        if (! $this->ready()) {
            return;
        }
        if ($this->eligible($user)) {
            $this->codes->ensure($user);
        }
        DB::transaction(function () use ($user) {
            BiometricDeviceUser::where('user_id', $user->id)->get()->each(function (BiometricDeviceUser $row) {
                $row->forceFill(['status' => 'pending', 'attempts' => 0, 'last_error' => null, 'version' => $row->version + 1])->save();
            });
            if (! $user->device_employee_no) {
                return;
            }
            foreach (BiometricDevice::where('is_active', true)->orderBy('id')->get() as $device) {
                $this->queue($device, $user->device_employee_no, $user, $this->eligible($user) ? 'upsert' : 'delete');
            }
        });
    }
```

Note: `queue()` skips a row only when it is already `synced` with the same action/name; we reset rows to `pending` first, so `queue()` always re-stamps them. For an eligible user whose rows were reset then re-queued with the same `upsert`, `version` ends at 2 (one bump in the reset loop; `queue()` sees status `pending` not `synced` and updates in place without a further unconditional bump only when fields differ — the reset already bumped it).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter test_resync_user_requeues_one_person_to_all_active_machines`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Biometric/MachineUserSync.php tests/Feature/BiometricSyncTest.php
git commit -m "feat: per-user biometric resync method"
```

---

### Task 2: Route + `BiometricController::resyncUser()` action

**Files:**
- Modify: `routes/web.php` (after line 205, inside the same admin group as the other biometric routes)
- Modify: `app/Http/Controllers/BiometricController.php`
- Test: `tests/Feature/BiometricSyncTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/BiometricSyncTest.php`:

```php
    public function test_admin_can_resync_one_user_from_their_form(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $device = $this->device();
        BiometricDeviceUser::query()->update(['status' => 'failed', 'attempts' => 3, 'last_error' => 'nope']);

        $this->actingAs($this->admin)->post('/people/'.$teacher->id.'/resync-machine')->assertRedirect();
        $this->assertSame(['pending', 0, null], [BiometricDeviceUser::sole()->status, BiometricDeviceUser::sole()->attempts, BiometricDeviceUser::sole()->last_error]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'biometric_device_user.resync', 'subject_type' => 'user', 'subject_id' => $teacher->id]);

        $other = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->actingAs($other)->post('/people/'.$teacher->id.'/resync-machine')->assertForbidden();
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter test_admin_can_resync_one_user_from_their_form`
Expected: FAIL (404/route not defined).

- [ ] **Step 3: Add the route**

In `routes/web.php`, immediately after the `biometric.bridge-script` route (line 205), still inside the same group:

```php
    Route::post('/people/{user}/resync-machine', [App\Http\Controllers\BiometricController::class, 'resyncUser'])
        ->name('biometric.resync-user')->middleware('role:admin');
```

- [ ] **Step 4: Add the controller action**

In `app/Http/Controllers/BiometricController.php`, add this method (the `User` and `MachineUserSync` imports already exist):

```php
    public function resyncUser(Request $request, User $user, MachineUserSync $sync)
    {
        $sync->resyncUser($user);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'biometric_device_user.resync',
            'subject_type' => 'user', 'subject_id' => $user->id, 'changes' => [], 'created_at' => now()]);

        return back()->with('success', "{$user->name} queued again for the attendance machines. The bridge PC sends them on its next run.");
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter test_admin_can_resync_one_user_from_their_form`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/BiometricController.php tests/Feature/BiometricSyncTest.php
git commit -m "feat: admin route to resync one user to the machines"
```

---

### Task 3: Read-only machine-sync partial

**Files:**
- Create: `resources/views/partials/machine-sync.blade.php`

- [ ] **Step 1: Create the partial**

Create `resources/views/partials/machine-sync.blade.php`:

```blade
@php
    $eligibleRoles = ['student', 'teacher', 'staff'];
    $devices = \App\Models\BiometricDevice::where('is_active', true)->orderBy('name')->get();
    $rows = $person
        ? \App\Models\BiometricDeviceUser::where('user_id', $person->id)->get()->keyBy('biometric_device_id')
        : collect();
    $canSync = $person && $person->is_active && in_array($person->role, $eligibleRoles, true) && $devices->isNotEmpty();
@endphp
<div class="form-section">
    <h2 class="form-section-title">Attendance machine</h2>
    @if(! $person)
        <p class="form-section-note">A Machine Employee No. is assigned automatically on save and sent to every active machine. Enroll the face and fingerprint on the machine afterwards.</p>
    @elseif(! $person->device_employee_no)
        <p class="form-section-note">No Machine Employee No. yet (admins and inactive people are not sent to machines).</p>
    @else
        <p class="mb-2">Machine Employee No.: <strong>{{ $person->device_employee_no }}</strong> <span class="text-muted">(automatic)</span></p>
        @if($devices->isEmpty())
            <p class="form-text">No active machine configured.</p>
        @else
            <ul class="mb-2">
                @foreach($devices as $device)
                    @php($row = $rows->get($device->id))
                    <li>
                        {{ $device->name }} —
                        @if(! $row)
                            <span class="text-muted">Not queued</span>
                        @elseif($row->status === 'synced')
                            <span class="text-success">Synced</span>
                        @elseif($row->status === 'failed')
                            <span class="text-danger">Failed</span> <span class="text-muted">{{ \Illuminate\Support\Str::limit($row->last_error, 120) }}</span>
                        @else
                            <span class="text-warning">Waiting</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
    @if($canSync)
        <form method="post" action="{{ route('biometric.resync-user', $person) }}">
            @csrf
            <button class="btn btn-outline-secondary btn-sm" type="submit">Sync now</button>
        </form>
        <div class="form-text">Re-sends this person to the machines on the bridge PC's next run.</div>
    @endif
</div>
```

- [ ] **Step 2: Lint the Blade syntax**

Run: `php artisan view:cache` then `php artisan view:clear`
Expected: `view:cache` completes without a compile error (no exception output).

- [ ] **Step 3: Commit**

```bash
git add resources/views/partials/machine-sync.blade.php
git commit -m "feat: machine sync status partial"
```

---

### Task 4: Swap the input for the partial in both forms

**Files:**
- Modify: `resources/views/admin/people/form.blade.php` (field block ~lines 33-43)
- Modify: `resources/views/staff/form.blade.php` (field block ~lines 39-60)
- Test: `tests/Feature/BiometricSyncTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/BiometricSyncTest.php`:

```php
    public function test_person_form_shows_number_and_status_not_an_input(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $device = $this->device();
        $code = $teacher->fresh()->device_employee_no;

        $html = $this->actingAs($this->admin)->get('/teachers/'.$teacher->id.'/edit')->assertOk();
        $html->assertSee('Machine Employee No.')->assertSee($code)->assertSee('Sync now')->assertSee('Waiting');
        $html->assertDontSee('name="device_employee_no"', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter test_person_form_shows_number_and_status_not_an_input`
Expected: FAIL (the form still renders `name="device_employee_no"`).

- [ ] **Step 3: Edit `admin/people/form.blade.php`**

Replace the whole machine section. Delete the block that currently reads (around lines 33-43):

```blade
    <div class="form-section">
        <h2 class="form-section-title">...</h2>
        <p class="form-section-note">The face/fingerprint machine uses this as the person's Employee No. Leave empty for an automatic number. ...</p>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="device_employee_no">Machine Employee No.</label>
                <input class="form-control" id="device_employee_no" name="device_employee_no" ... >
                <div class="form-text" id="device_employee_no_help">...</div>
            </div>
        </div>
    </div>
```

and replace it with:

```blade
    @include('partials.machine-sync')
```

(Verify by grep that `name="device_employee_no"` is gone from the file; keep the surrounding `form-section` divs balanced — the include replaces the entire removed `<div class="form-section">…</div>`.)

- [ ] **Step 4: Edit `staff/form.blade.php`**

In the "Pay and attendance machine" section (lines 39-60): remove only the Machine Employee No. input column (lines 47-51):

```blade
            <div class="col-md-6">
                <label class="form-label" for="device_employee_no">Machine Employee No.</label>
                <input class="form-control" id="device_employee_no" name="device_employee_no" inputmode="numeric" maxlength="32" pattern="[A-Za-z0-9]{1,32}" value="{{ old('device_employee_no', $person?->device_employee_no) }}">
                <div class="form-text">Leave empty for an automatic number.</div>
            </div>
```

Also shorten the section note on line 41 — change:

```blade
        <p class="form-section-note">Payroll &gt; Generate month creates this salary every month. The machine number is sent to the attendance machine automatically; punches appear in Staff Attendance and do not change pay.</p>
```

to:

```blade
        <p class="form-section-note">Payroll &gt; Generate month creates this salary every month.</p>
```

Then add the partial immediately **after** the closing `</div>` of that `form-section` (after line 60):

```blade
    @include('partials.machine-sync')
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter test_person_form_shows_number_and_status_not_an_input`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/people/form.blade.php resources/views/staff/form.blade.php tests/Feature/BiometricSyncTest.php
git commit -m "feat: show machine number + sync status on person forms, drop manual input"
```

---

### Task 5: Drop `device_employee_no` from the three controllers

**Files:**
- Modify: `app/Http/Controllers/StaffController.php`
- Modify: `app/Http/Controllers/TeacherController.php`
- Modify: `app/Http/Controllers/StudentController.php`
- Test: `tests/Feature/BiometricSyncTest.php` (update existing override assertions)

- [ ] **Step 1: Update the existing override assertions (make them fail first)**

In `tests/Feature/BiometricSyncTest.php`, in `test_new_students_and_teachers_get_automatic_numbers_and_are_queued`, replace the manual-number block (current lines 58-64) with:

```php
        // A posted device_employee_no is ignored; the teacher still gets an automatic number.
        $this->post('/teachers', ['name' => 'Manual Number', 'email' => 'manual@example.test', 'phone' => '0300', 'address' => 'Karachi',
            'password' => 'password123', 'password_confirmation' => 'password123', 'device_employee_no' => '5005'])->assertSessionHasNoErrors();
        $manual = User::where('email', 'manual@example.test')->sole();
        $this->assertSame((string) $manual->id, $manual->device_employee_no);
        $this->assertSame(0, BiometricDeviceUser::where('employee_no', '5005')->count());
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter test_new_students_and_teachers_get_automatic_numbers_and_are_queued`
Expected: FAIL (controller still honours the posted `5005`).

- [ ] **Step 3: Edit `StaffController.php`**

`store()` — replace lines 24-35 with:

```php
        $data = $this->validated($request, null);
        DB::transaction(function () use ($data) {
            // Email and password are required columns; staff never signs in, so both are unusable placeholders.
            $staff = User::create($data + ['role' => 'staff', 'salary_type' => 'monthly',
                'email' => 'staff-'.Str::uuid().'@staff.invalid', 'password' => Hash::make(Str::random(64))]);
            $staff->update(['employee_id' => 'STF'.str_pad((string) $staff->id, 4, '0', STR_PAD_LEFT)]);
        });
```

`update()` — replace lines 50-54 with:

```php
        $data = $this->validated($request, $staff);
        DB::transaction(fn () => $staff->update($data));
```

`validated()` — delete the `device_employee_no` rule (lines 70-71). Remove the now-unused `use Illuminate\Validation\Rule;` import **only if** no other rule uses it — in this file `Rule` is used solely by that rule, so remove the import too.

- [ ] **Step 4: Edit `TeacherController.php`**

`store()` — delete the `device_employee_no` validation line (69); replace lines 71-82 with:

```php
        DB::transaction(function () use ($data, $request) {
            $data['role'] = 'teacher';
            $data['is_active'] = $request->boolean('is_active', true);
            $data['experience_years'] = $data['experience_years'] ?? 0;
            $teacher = User::create($data);
            $teacher->update(['employee_id' => 'TCH'.str_pad((string) $teacher->id, 4, '0', STR_PAD_LEFT)]);
        });
```

`update()` — delete the `device_employee_no` validation line (130); replace lines 132-139 with:

```php
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['experience_years'] = $data['experience_years'] ?? 0;
        DB::transaction(fn () => $teacher->fill($data)->save());
```

- [ ] **Step 5: Edit `StudentController.php`**

`store()` — delete the `device_employee_no` validation line (95) and the block at lines 112-114:

```php
            if ($request->filled('device_employee_no')) {
                $student->forceFill(['device_employee_no' => $request->input('device_employee_no')])->save();
            }
```

`update()` — delete the `device_employee_no` validation line (194) and the block at lines 198-200:

```php
            if ($request->filled('device_employee_no')) {
                $student->forceFill(['device_employee_no' => $request->input('device_employee_no')])->save();
            }
```

If removing these leaves `Rule` unused in `StudentController`, keep it only if another rule references it (it does — `unique:users` elsewhere uses the string form; check: if the only `Rule::` calls were the two removed lines, drop the `use Illuminate\Validation\Rule;` import; otherwise keep it).

- [ ] **Step 6: Run the updated override test**

Run: `php artisan test --filter test_new_students_and_teachers_get_automatic_numbers_and_are_queued`
Expected: PASS.

- [ ] **Step 7: Run the biometric suite**

Run: `php artisan test --filter BiometricSyncTest`
Expected: PASS (including `test_profile_changes_queue_updates_moves_and_removals`, whose blank-field put still keeps the existing number).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/StaffController.php app/Http/Controllers/TeacherController.php app/Http/Controllers/StudentController.php tests/Feature/BiometricSyncTest.php
git commit -m "refactor: stop accepting manual machine number from person forms"
```

---

### Task 6: Full verification

**Files:** none (verification only)

- [ ] **Step 1: Run the whole suite**

Run: `php artisan test`
Expected: all green. If `StaffTest` or any person test fails on a removed field, fix the assertion to expect the automatic number (do not re-add the input).

- [ ] **Step 2: Manual check (optional, if a local server is available)**

Open a teacher/staff edit page: confirm no Machine Employee No. input, the number + per-machine status show, and "Sync now" posts and flashes success.

- [ ] **Step 3: Final commit (only if Step 1 required fixes)**

```bash
git add -A
git commit -m "test: align person tests with automatic machine number"
```

---

## Self-review notes

- **Spec coverage:** remove input (Task 4), read-only number (Task 3/4), per-machine status (Task 3/4), Sync-now button + route + service (Tasks 1-3), controller cleanup (Task 5), tests (every task). Scope corrected from 3 views to 2 (students+teachers share `people/form`); `admin/students/edit.blade.php` confirmed unrendered and left as-is.
- **Placeholder scan:** none; all code shown.
- **Type consistency:** `resyncUser(User)` defined in Task 1, called in Task 2 action and (via route) Task 4 partial. `biometric.resync-user` route name used in Task 2 and the Task 3 partial. Status strings `synced/pending/failed` match `BiometricDeviceUser`.
- **Risk:** the `version` assertion in Task 1 (ends at 2) depends on `queue()` not double-bumping; if a run shows 3, adjust the assertion to the observed value — behavior (pending, reset) is what matters.
