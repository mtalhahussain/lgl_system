# Student Certificates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an Admin issue a course-completion certificate to a student, rendered as an A4-landscape print page over the owner's `template.png` background, with a saved record for reprint and audit.

**Architecture:** A new `student_certificates` table stores one snapshot row per issue (student name, father name, level, date, unique number, issuer). A single `CertificateController` (admin-only) serves an issue form + issued list, stores a snapshot with a safely generated number, writes an audit row, and renders a standalone print view. The print view inlines `template.png` as a base64 background (no `storage:link` dependency) and overlays a fixed German text block. No exam/marks logic, no PDF package.

**Tech Stack:** Laravel 12, PHP 8.2, Eloquent, Blade + Bootstrap, SQLite (`:memory:`) for tests, PHPUnit.

---

## File Structure

- **Create** `database/migrations/2026_11_05_000100_create_student_certificates_table.php` — new table.
- **Create** `app/Models/StudentCertificate.php` — model + `nextNumber()` generator + relations.
- **Create** `app/Http/Controllers/CertificateController.php` — `create` (form+list), `store`, `print`.
- **Modify** `routes/web.php` — three admin-only routes inside the existing `auth` group.
- **Create** `resources/views/certificates/index.blade.php` — issue form + issued list (uses `layouts.admin`).
- **Create** `resources/views/certificates/print.blade.php` — standalone A4-landscape print page (no app chrome).
- **Modify** `resources/views/layouts/admin.blade.php` — admin-only "Certificates" sidebar link.
- **Modify** `resources/views/admin/people/profile.blade.php` — "Issue Certificate" button on the student profile.
- **Create** `tests/Feature/CertificateTest.php` — feature tests.

**Conventions to follow (verified in this codebase):**
- Routes use inline FQN controller references (e.g. `App\Http\Controllers\CertificateController::class`) and `->middleware('role:admin')`.
- Audit rows: `AuditLog::create(['actor_id' => $request->user()->id, 'action' => '…', 'subject_type' => '…', 'subject_id' => …, 'changes' => []])`. `action`/`subject_type` are ≤ 80 chars.
- `AuditLog` is immutable (cannot be updated/deleted) and `created_at` auto-fills.
- Student level path: `Enrollment → batch → course.level` (`course.level` enum `A1,A2,B1,B2,C1,C2`).
- `User::students()` scope and `isStudent()`/`isAdmin()` helpers exist.
- Standalone print pattern: a `d-print-none` action bar with `onclick="window.print()"`.

---

## Task 1: Migration and model

**Files:**
- Create: `database/migrations/2026_11_05_000100_create_student_certificates_table.php`
- Create: `app/Models/StudentCertificate.php`
- Test: `tests/Feature/CertificateTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CertificateTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\StudentCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_row_persists_its_snapshot_fields(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);

        $cert = StudentCertificate::create([
            'student_id' => $student->id,
            'student_name' => 'Syed Saad',
            'father_name' => 'Syed Mehboob',
            'level' => 'A1',
            'completion_date' => '2026-10-27',
            'certificate_number' => 'LGL-A1-2026-001',
            'issued_by' => $admin->id,
            'issued_at' => now(),
        ]);

        $fresh = $cert->fresh();
        $this->assertSame('Syed Saad', $fresh->student_name);
        $this->assertSame('Syed Mehboob', $fresh->father_name);
        $this->assertSame('A1', $fresh->level);
        $this->assertSame('2026-10-27', $fresh->completion_date->format('Y-m-d'));
        $this->assertSame($student->id, $fresh->student->id);
        $this->assertSame($admin->id, $fresh->issuer->id);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_certificate_row_persists_its_snapshot_fields`
Expected: FAIL — `Class "App\Models\StudentCertificate" not found`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_11_05_000100_create_student_certificates_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_name');
            $table->string('father_name')->nullable();
            $table->string('level', 8);
            $table->date('completion_date');
            $table->string('certificate_number')->unique();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_certificates');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/StudentCertificate.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class StudentCertificate extends Model
{
    protected $fillable = [
        'student_id', 'student_name', 'father_name', 'level',
        'completion_date', 'certificate_number', 'issued_by', 'issued_at',
    ];

    protected function casts(): array
    {
        return ['completion_date' => 'date', 'issued_at' => 'datetime'];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Next human-readable number for a level, e.g. LGL-A1-2026-001.
     * Skips any number already taken (the unique index is the final guard).
     */
    public static function nextNumber(string $level): string
    {
        $prefix = 'LGL-'.$level.'-'.Carbon::now()->year.'-';
        $seq = static::where('certificate_number', 'like', $prefix.'%')->count() + 1;

        do {
            $candidate = $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $seq++;
        } while (static::where('certificate_number', $candidate)->exists());

        return $candidate;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=test_certificate_row_persists_its_snapshot_fields`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_11_05_000100_create_student_certificates_table.php app/Models/StudentCertificate.php tests/Feature/CertificateTest.php
git commit -m "feat: add student_certificates table and model"
```

---

## Task 2: Number generator skips taken numbers

**Files:**
- Test: `tests/Feature/CertificateTest.php`
- (No source change expected — `nextNumber()` from Task 1 should already pass.)

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/CertificateTest.php`:

```php
public function test_next_number_is_sequential_per_level_and_year_and_skips_taken(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $base = ['student_name' => 'X', 'level' => 'A1', 'completion_date' => '2026-10-27',
        'issued_by' => $admin->id, 'issued_at' => now()];

    $this->assertSame('LGL-A1-'.now()->year.'-001', StudentCertificate::nextNumber('A1'));

    StudentCertificate::create($base + ['certificate_number' => 'LGL-A1-'.now()->year.'-001']);
    $this->assertSame('LGL-A1-'.now()->year.'-002', StudentCertificate::nextNumber('A1'));

    // A gap/collision on the computed sequence is skipped.
    StudentCertificate::create($base + ['certificate_number' => 'LGL-A1-'.now()->year.'-002']);
    StudentCertificate::create($base + ['level' => 'A2', 'certificate_number' => 'LGL-A1-'.now()->year.'-003']);
    $this->assertSame('LGL-A1-'.now()->year.'-004', StudentCertificate::nextNumber('A1'));

    // A different level starts its own sequence.
    $this->assertSame('LGL-A2-'.now()->year.'-002', StudentCertificate::nextNumber('A2'));
}
```

- [ ] **Step 2: Run the test**

Run: `php artisan test --filter=test_next_number_is_sequential_per_level_and_year_and_skips_taken`
Expected: PASS (confirms Task 1's `nextNumber()` handles collisions). If it FAILS, fix `nextNumber()` in `app/Models/StudentCertificate.php` until it passes — do not change the test.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/CertificateTest.php
git commit -m "test: cover certificate number generation"
```

---

## Task 3: Controller and routes (issue + store + print)

**Files:**
- Create: `app/Http/Controllers/CertificateController.php`
- Modify: `routes/web.php` (add three routes after the biometric block, before the group's closing `});` at line ~230)
- Test: `tests/Feature/CertificateTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/CertificateTest.php` (add `use App\Models\AuditLog;`, `use App\Models\Batch;`, `use App\Models\Course;`, `use App\Models\Enrollment;` to the imports):

```php
public function test_admin_issues_a_certificate_and_is_redirected_to_print(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student', 'name' => 'Sara']);

    $response = $this->actingAs($admin)->post('/certificates', [
        'student_id' => $student->id,
        'student_name' => 'Sara Khan',
        'father_name' => 'Imran Khan',
        'level' => 'A1',
        'completion_date' => '2026-10-27',
    ]);

    $cert = StudentCertificate::sole();
    $response->assertRedirect('/certificates/'.$cert->id.'/print');
    $this->assertSame('Sara Khan', $cert->student_name);
    $this->assertSame('Imran Khan', $cert->father_name);
    $this->assertSame('A1', $cert->level);
    $this->assertSame($admin->id, $cert->issued_by);
    $this->assertSame('LGL-A1-'.now()->year.'-001', $cert->certificate_number);
    $this->assertSame(1, AuditLog::where('action', 'certificate.issued')
        ->where('subject_type', 'student_certificate')->where('subject_id', $cert->id)->count());
}

public function test_issue_form_prefills_level_from_the_students_enrollment(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student', 'name' => 'Sara']);
    $course = Course::create(['name' => 'German B1', 'level' => 'B1', 'total_fee' => 30000,
        'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);
    $batch = Batch::create(['name' => 'B1 Morning', 'course_id' => $course->id, 'teacher_id' => $admin->id,
        'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'ongoing', 'max_students' => 20]);
    Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id,
        'enrollment_date' => '2026-10-01', 'total_fee' => 30000, 'status' => 'active']);

    $this->actingAs($admin)->get('/certificates?student='.$student->id)
        ->assertOk()->assertSee('Sara')->assertSee('B1');
}

public function test_store_validates_level_and_required_fields(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($admin)->post('/certificates', [
        'student_id' => $student->id, 'student_name' => '', 'level' => 'Z9', 'completion_date' => 'nope',
    ])->assertSessionHasErrors(['student_name', 'level', 'completion_date']);

    $this->assertSame(0, StudentCertificate::count());
}

public function test_snapshot_survives_a_later_student_name_change(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student', 'name' => 'Old Name']);

    $this->actingAs($admin)->post('/certificates', ['student_id' => $student->id,
        'student_name' => 'Old Name', 'father_name' => 'Dad', 'level' => 'A1', 'completion_date' => '2026-10-27']);
    $student->update(['name' => 'Renamed']);

    $this->assertSame('Old Name', StudentCertificate::sole()->student_name);
}

public function test_non_admins_cannot_issue_or_view_certificates(): void
{
    $teacher = User::factory()->create(['role' => 'teacher']);
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($teacher)->get('/certificates')->assertForbidden();
    $this->actingAs($student)->get('/certificates')->assertForbidden();
    $this->actingAs($teacher)->post('/certificates', ['student_id' => $student->id,
        'student_name' => 'X', 'level' => 'A1', 'completion_date' => '2026-10-27'])->assertForbidden();
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=CertificateTest`
Expected: the new tests FAIL (route `/certificates` returns 404).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/CertificateController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StudentCertificate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CertificateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    public function create(Request $request)
    {
        $students = User::students()
            ->with(['enrollments' => fn ($q) => $q->orderByDesc('enrollment_date')->with('batch.course:id,level')])
            ->orderBy('name')->get(['id', 'name']);

        // id => ['name' => ..., 'level' => ...] for the form's auto-fill.
        $studentMeta = $students->mapWithKeys(fn ($s) => [$s->id => [
            'name' => $s->name,
            'level' => optional($s->enrollments->first()?->batch?->course)->level ?? '',
        ]]);

        $preselect = $request->filled('student') ? $studentMeta->get((int) $request->query('student')) : null;
        $preselectId = $preselect ? (int) $request->query('student') : null;

        $certificates = StudentCertificate::with('student:id,name')
            ->orderByDesc('issued_at')->limit(50)->get();

        return view('certificates.index', compact('students', 'studentMeta', 'preselect', 'preselectId', 'certificates'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'nullable|exists:users,id',
            'student_name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'level' => 'required|in:A1,A2,B1,B2,C1,C2',
            'completion_date' => 'required|date',
        ]);

        $cert = DB::transaction(function () use ($data, $request) {
            $cert = StudentCertificate::create([
                'student_id' => $data['student_id'] ?? null,
                'student_name' => $data['student_name'],
                'father_name' => $data['father_name'] ?? null,
                'level' => $data['level'],
                'completion_date' => $data['completion_date'],
                'certificate_number' => StudentCertificate::nextNumber($data['level']),
                'issued_by' => $request->user()->id,
                'issued_at' => now(),
            ]);

            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'certificate.issued',
                'subject_type' => 'student_certificate', 'subject_id' => $cert->id, 'changes' => []]);

            return $cert;
        });

        return redirect()->route('certificates.print', $cert)
            ->with('success', 'Certificate '.$cert->certificate_number.' issued.');
    }

    public function print(StudentCertificate $certificate)
    {
        return view('certificates.print', ['cert' => $certificate]);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/web.php`, immediately after the `biometric.resync-user` route (the `POST /people/{user}/resync-machine` line, ~206) and before the group's closing `});`, add:

```php
    Route::get('/certificates', [App\Http\Controllers\CertificateController::class, 'create'])
        ->name('certificates.index')->middleware('role:admin');
    Route::post('/certificates', [App\Http\Controllers\CertificateController::class, 'store'])
        ->name('certificates.store')->middleware('role:admin');
    Route::get('/certificates/{certificate}/print', [App\Http\Controllers\CertificateController::class, 'print'])
        ->name('certificates.print')->middleware('role:admin');
```

- [ ] **Step 5: Create a minimal print view placeholder (so `print` renders during this task)**

Create `resources/views/certificates/print.blade.php` (replaced fully in Task 5; minimal now so the redirect target resolves):

```blade
<!doctype html>
<html lang="de"><head><meta charset="utf-8"><title>Certificate {{ $cert->certificate_number }}</title></head>
<body>{{ $cert->student_name }} — Niveau {{ $cert->level }} — {{ $cert->completion_date->format('d.m.Y') }}</body></html>
```

- [ ] **Step 6: Create a minimal index view placeholder (so `create` renders; replaced in Task 4)**

Create `resources/views/certificates/index.blade.php`:

```blade
@extends('layouts.admin')
@section('title', 'Certificates')
@section('content')
<div>{{ $preselect['name'] ?? '' }} {{ $preselect['level'] ?? '' }}</div>
<form method="post" action="{{ route('certificates.store') }}">@csrf</form>
@endsection
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter=CertificateTest`
Expected: all CertificateTest methods PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/CertificateController.php routes/web.php resources/views/certificates/
git commit -m "feat: certificate issue, store and print routes (admin only)"
```

---

## Task 4: Issue form and issued list view

**Files:**
- Modify: `resources/views/certificates/index.blade.php` (replace the Task 3 placeholder)
- Test: `tests/Feature/CertificateTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/CertificateTest.php`:

```php
public function test_index_shows_the_form_and_the_issued_list_with_reprint_links(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student', 'name' => 'Sara']);
    $cert = StudentCertificate::create(['student_id' => $student->id, 'student_name' => 'Sara Khan',
        'father_name' => 'Imran', 'level' => 'A1', 'completion_date' => '2026-10-27',
        'certificate_number' => 'LGL-A1-2026-007', 'issued_by' => $admin->id, 'issued_at' => now()]);

    $this->actingAs($admin)->get('/certificates')->assertOk()
        ->assertSee('Issue Certificate')
        ->assertSee('LGL-A1-2026-007')
        ->assertSee('Sara Khan')
        ->assertSee(route('certificates.print', $cert), false);
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_index_shows_the_form_and_the_issued_list_with_reprint_links`
Expected: FAIL (placeholder view lacks "Issue Certificate" and the number).

- [ ] **Step 3: Replace the index view**

Overwrite `resources/views/certificates/index.blade.php`:

```blade
@extends('layouts.admin')
@section('title', 'Certificates')
@section('content')
<div class="panel-heading"><h1>Issue Certificate</h1></div>

<section class="panel" style="padding:18px 22px;max-width:720px">
    <form method="post" action="{{ route('certificates.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="student_id">Student</label>
            <select class="form-select" id="student_id" name="student_id">
                <option value="">— Select a student —</option>
                @foreach($students as $s)
                    <option value="{{ $s->id }}" @selected((int) old('student_id', $preselectId) === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
            <div class="form-text">Selecting a student fills the name and level below; you can edit them.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="student_name">Name on certificate</label>
            <input class="form-control" id="student_name" name="student_name"
                   value="{{ old('student_name', $preselect['name'] ?? '') }}" required maxlength="255">
            @error('student_name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="father_name">Father&rsquo;s name (S/O)</label>
            <input class="form-control" id="father_name" name="father_name"
                   value="{{ old('father_name') }}" maxlength="255">
        </div>
        <div class="row">
            <div class="col-sm-6 mb-3">
                <label class="form-label" for="level">Level</label>
                <select class="form-select" id="level" name="level" required>
                    @foreach(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as $lvl)
                        <option value="{{ $lvl }}" @selected(old('level', $preselect['level'] ?? 'A1') === $lvl)>{{ $lvl }}</option>
                    @endforeach
                </select>
                @error('level')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 mb-3">
                <label class="form-label" for="completion_date">Completion date</label>
                <input class="form-control" id="completion_date" name="completion_date" type="date"
                       value="{{ old('completion_date', now()->toDateString()) }}" required>
                @error('completion_date')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-award me-1" aria-hidden="true"></i>Issue &amp; print</button>
    </form>
</section>

<section class="panel mt-4" aria-labelledby="issued-heading">
    <div class="panel-heading"><h2 id="issued-heading">Issued certificates</h2><span class="page-subtitle">{{ $certificates->count() }} shown</span></div>
    <div class="table-responsive"><table class="table list-table">
        <thead><tr><th scope="col">Number</th><th scope="col">Student</th><th scope="col">Level</th><th scope="col">Completed</th><th scope="col">Issued</th><th scope="col" class="d-print-none">&nbsp;</th></tr></thead>
        <tbody>
        @forelse($certificates as $certificate)
            <tr>
                <td class="cell-main">{{ $certificate->certificate_number }}</td>
                <td>{{ $certificate->student_name }}@if($certificate->father_name)<span class="cell-sub">S/O {{ $certificate->father_name }}</span>@endif</td>
                <td>{{ $certificate->level }}</td>
                <td>{{ $certificate->completion_date->format('d M Y') }}</td>
                <td>{{ $certificate->issued_at?->format('d M Y') }}</td>
                <td class="d-print-none"><a class="btn btn-outline-secondary btn-sm" href="{{ route('certificates.print', $certificate) }}" target="_blank" rel="noopener"><i class="fas fa-print me-1" aria-hidden="true"></i>Reprint</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty-state">No certificates issued yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>

<script>
    (function () {
        var meta = @json($studentMeta);
        var sel = document.getElementById('student_id');
        var name = document.getElementById('student_name');
        var level = document.getElementById('level');
        if (!sel) return;
        sel.addEventListener('change', function () {
            var m = meta[sel.value];
            if (!m) return;
            if (name && !name.value) name.value = m.name || '';
            name && (name.value = m.name || name.value);
            if (level && m.level) level.value = m.level;
        });
    })();
</script>
@endsection
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=test_index_shows_the_form_and_the_issued_list_with_reprint_links`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/certificates/index.blade.php tests/Feature/CertificateTest.php
git commit -m "feat: certificate issue form and issued list"
```

---

## Task 5: A4-landscape print view with template overlay

**Files:**
- Modify: `resources/views/certificates/print.blade.php` (replace the Task 3 placeholder)
- Test: `tests/Feature/CertificateTest.php`

**Notes:**
- The template is inlined as base64 from `storage_path('app/public/settings/template.png')` so it works without `php artisan storage:link`. If the file is missing, the page still renders on a plain white background.
- Page is A4 landscape (297mm × 210mm). The text block is absolutely positioned over the empty centre/right of the template. Positions below are a sensible starting point tuned to the owner's reference image; the implementer should open the print page in a browser and nudge the `--x`/`top` values so nothing overlaps the logo, signature or seal.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/CertificateTest.php`:

```php
public function test_print_view_renders_the_snapshot_text(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $cert = StudentCertificate::create(['student_id' => null, 'student_name' => 'Syed Saad',
        'father_name' => 'Syed Mehboob', 'level' => 'A1', 'completion_date' => '2026-10-27',
        'certificate_number' => 'LGL-A1-2026-009', 'issued_by' => $admin->id, 'issued_at' => now()]);

    $this->actingAs($admin)->get('/certificates/'.$cert->id.'/print')->assertOk()
        ->assertSee('Teilnahmebescheinigung')
        ->assertSee('Syed Saad')
        ->assertSee('Syed Mehboob')
        ->assertSee('Niveau A1')
        ->assertSee('27.10.2026');
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_print_view_renders_the_snapshot_text`
Expected: FAIL (placeholder lacks "Teilnahmebescheinigung").

- [ ] **Step 3: Replace the print view**

Overwrite `resources/views/certificates/print.blade.php`:

```blade
@php
    $templatePath = storage_path('app/public/settings/template.png');
    $templateSrc = is_file($templatePath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($templatePath))
        : null;
    $soLine = trim($cert->student_name.($cert->father_name ? ' S/O '.$cert->father_name : ''));
    $dateDe = $cert->completion_date->format('d.m.Y');
@endphp
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate {{ $cert->certificate_number }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { background: #e9e9e9; font-family: 'Georgia', 'Times New Roman', serif; color: #1a1a1a; }
        .sheet {
            position: relative;
            width: 297mm; height: 210mm;
            margin: 0 auto;
            background: #fff center/cover no-repeat;
            @if($templateSrc) background-image: url('{{ $templateSrc }}'); @endif
            overflow: hidden;
        }
        /* Text block over the empty centre/right of the template. */
        .cert-body {
            position: absolute;
            top: 46mm; left: 120mm; right: 18mm;
        }
        .cert-title { font-size: 30pt; font-weight: 700; margin: 0 0 10mm; letter-spacing: .3pt; }
        .cert-grats { font-size: 15pt; font-style: italic; margin: 0 0 2mm; }
        .cert-name { color: #c0202a; font-weight: 700; font-style: italic; }
        .cert-line { font-size: 14pt; margin: 0 0 1mm; }
        .cert-course { font-size: 15pt; font-weight: 700; margin: 0 0 8mm; }
        .cert-date { font-size: 13pt; margin: 0 0 8mm; }
        .cert-date strong { font-weight: 700; }
        .cert-text { font-size: 11.5pt; line-height: 1.5; max-width: 150mm; }
        .cert-number { position: absolute; bottom: 6mm; right: 10mm; font-size: 8pt; color: #888; }
        .toolbar { text-align: center; padding: 14px; }
        .toolbar button { font: inherit; padding: 8px 18px; cursor: pointer; }
        @media print { .toolbar { display: none; } body { background: #fff; } .sheet { margin: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>
    <div class="sheet">
        <div class="cert-body">
            <h1 class="cert-title">Teilnahmebescheinigung</h1>
            <p class="cert-grats">Herzlichen Glückwunsch, <span class="cert-name">{{ $soLine }}</span></p>
            <p class="cert-line">zum erfolgreichen Abschluss des</p>
            <p class="cert-course">Deutsch-Sprachkurses &ndash; Niveau {{ $cert->level }}</p>
            <p class="cert-date">Kurs abgeschlossen am <strong>{{ $dateDe }}</strong></p>
            <p class="cert-text">
                Ihr Einsatz beim Erlernen der deutschen Sprache war lobenswert. Auf dem
                {{ $cert->level }}-Niveau haben Sie grundlegende sprachliche Fähigkeiten erworben
                und erste einfache Kommunikationssituationen sicher gemeistert.
            </p>
        </div>
        <div class="cert-number">{{ $cert->certificate_number }}</div>
    </div>
</body>
</html>
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=test_print_view_renders_the_snapshot_text`
Expected: PASS.

- [ ] **Step 5: Visually verify the overlay (manual)**

Run the app and open an issued certificate's print page. Confirm the text sits in the empty area and does not overlap the logo (top-left), the signature, or the seal (bottom). If it does, adjust `.cert-body { top / left / right }` and font sizes in the view, then re-run `php artisan test --filter=test_print_view_renders_the_snapshot_text` to confirm it still passes.

- [ ] **Step 6: Commit**

```bash
git add resources/views/certificates/print.blade.php tests/Feature/CertificateTest.php
git commit -m "feat: A4 certificate print view over template background"
```

---

## Task 6: Sidebar link and student-profile button

**Files:**
- Modify: `resources/views/layouts/admin.blade.php` (admin MANAGEMENT-area nav; insert a "Certificates" item near the Staff Attendance / Reports admin links, ~line 183)
- Modify: `resources/views/admin/people/profile.blade.php` (student profile actions, ~line 48-51)
- Test: `tests/Feature/CertificateTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/CertificateTest.php`:

```php
public function test_student_profile_shows_issue_certificate_shortcut_for_admin_only(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($admin)->get('/students/'.$student->id)
        ->assertOk()
        ->assertSee('Issue Certificate')
        ->assertSee(route('certificates.index', ['student' => $student->id]), false);

    $teacherViewer = User::factory()->create(['role' => 'teacher']);
    // Enroll the student in the teacher's batch so the teacher may view the profile.
    $course = \App\Models\Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 30000,
        'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);
    $batch = \App\Models\Batch::create(['name' => 'A1', 'course_id' => $course->id, 'teacher_id' => $teacherViewer->id,
        'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'ongoing', 'max_students' => 20]);
    \App\Models\Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id,
        'enrollment_date' => '2026-10-01', 'total_fee' => 30000, 'status' => 'active']);

    $this->actingAs($teacherViewer)->get('/students/'.$student->id)
        ->assertOk()->assertDontSee('Issue Certificate');
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_student_profile_shows_issue_certificate_shortcut_for_admin_only`
Expected: FAIL (button not present).

- [ ] **Step 3: Add the student-profile button**

In `resources/views/admin/people/profile.blade.php`, inside the `@elseif(! $isTeacherProfile)` branch of `.profile-actions` (after the Attendance link, ~line 50), add:

```blade
            @if($isAdmin)<a class="btn btn-outline-primary" href="{{ route('certificates.index', ['student' => $person->id]) }}"><i class="fas fa-award me-1" aria-hidden="true"></i>Issue Certificate</a>@endif
```

- [ ] **Step 4: Add the sidebar link**

In `resources/views/layouts/admin.blade.php`, after the admin "Staff Attendance" `@if(...@endif` block (the `</li>` then `@endif` around line 182-183) and before the Reports block, add:

```blade
                        @if(auth()->user()->isAdmin())
                        <li class="nav-item"><a class="nav-link {{ request()->is('certificates*') ? 'active' : '' }}" href="{{ route('certificates.index') }}"><i class="fas fa-award me-2" aria-hidden="true"></i>Certificates</a></li>
                        @endif
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=test_student_profile_shows_issue_certificate_shortcut_for_admin_only`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/admin.blade.php resources/views/admin/people/profile.blade.php tests/Feature/CertificateTest.php
git commit -m "feat: certificates sidebar link and student-profile shortcut"
```

---

## Task 7: Full verification

**Files:** none (verification only).

- [ ] **Step 1: Run the whole certificate suite**

Run: `php artisan test --filter=CertificateTest`
Expected: all tests PASS.

- [ ] **Step 2: Run the full test suite (guard against regressions)**

Run: `php artisan test`
Expected: PASS, except the pre-existing, date-dependent `BatchScheduleTest::test_generate_from_schedule...` failure already documented in docs/TASKS.md (unrelated to this branch — this branch touches no batch files). Confirm no *other* test regressed.

- [ ] **Step 3: Lint the changed PHP (if Pint is configured)**

Run: `vendor/bin/pint --dirty`
Expected: no outstanding style issues on changed files.

- [ ] **Step 4: Commit any fixes**

```bash
git add -A
git commit -m "chore: verification and style for student certificates"
```

---

## Self-Review (completed during planning)

- **Spec coverage:** issuance flow (Tasks 3-4), A4 template overlay print (Task 5), `student_certificates` snapshot + unique number (Tasks 1-2), audit on issue (Task 3), sidebar + student-profile entry points (Task 6), admin-only access (Task 3), level pre-fill from enrolment (Tasks 3-4), legacy tables untouched (new distinct table name, Task 1). All covered.
- **Placeholder scan:** no TBD/TODO; every code step shows full code.
- **Type consistency:** `nextNumber(string $level): string`, `student_name`/`father_name`/`level`/`completion_date`/`certificate_number`/`issued_by`/`issued_at` used identically across migration, model, controller, views and tests. Route names `certificates.index` / `certificates.store` / `certificates.print` consistent throughout.
- **Out of scope confirmed:** no exam/marks, no signature upload, no PDF package, no teacher/student certificate access.
```
