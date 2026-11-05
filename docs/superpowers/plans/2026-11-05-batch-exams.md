# Batch Exams Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an Admin create a batch exam with four fixed CEFR sections (Hören/Lesen/Schreiben/Sprechen, per-exam editable marks), have the batch teacher or Admin enter each student's section marks, compute a section-wise pass (all four sections must pass), and issue the existing certificate from a passing result — with the certificate linked to the result and revocable.

**Architecture:** Four new tables (`batch_exams`, `exam_sections`, `student_exam_results`, `student_exam_section_marks`) with matching Eloquent models; a thin `ExamGradingService` centralising pass computation, the save transaction, audit, and the pass→fail correction guard; a single `ExamController` (Admin create/edit, Admin+own-batch-teacher result entry); the existing `CertificateController` extended to issue from a result (with a duplicate guard) and to revoke. The legacy empty `exams`/`exam_results` tables and models are left dormant.

**Tech Stack:** Laravel 12, PHP 8.2, Eloquent, Blade + Bootstrap, PHPUnit with SQLite `:memory:`, Pint.

**Design spec:** [docs/superpowers/specs/2026-11-05-batch-exams-design.md](../specs/2026-11-05-batch-exams-design.md)

**Conventions verified in this codebase:**
- Shared Admin+Teacher routes use `->middleware('role:admin,teacher')`; Admin-only use `->middleware('role:admin')`. Routes are inside the existing `auth` group in `routes/web.php`; exam routes go after the Certificate block (~line 215), the student result route inside the `role:student` group (~line 230).
- Teacher ownership check: `abort_if(auth()->user()->isTeacher() && $batch->teacher_id !== auth()->id(), 403);`.
- `User` has `students()` scope and `isAdmin()`/`isTeacher()`/`isStudent()`.
- `Enrollment` has `student_id`, `batch_id`, `status` (`active`/`completed`/…). `Batch` has `teacher_id`, `course()`; `Course` has `level` (`A1,A2,B1,B2,C1,C2`).
- Audit rows: `AuditLog::create(['actor_id' => $actor->id, 'action' => '…', 'subject_type' => '…', 'subject_id' => $id, 'changes' => []])`. `action`/`subject_type` ≤ 80 chars; `AuditLog` is immutable.
- `StudentCertificate` exists (table `student_certificates`): columns `student_id, student_name, father_name, level, completion_date, certificate_number, issued_by, issued_at`; static `nextNumber(string $level)`; relations `student()`, `issuer()`.

---

## File Structure

- **Create** `database/migrations/2026_11_05_000200_create_batch_exams_table.php`
- **Create** `database/migrations/2026_11_05_000201_create_exam_sections_table.php`
- **Create** `database/migrations/2026_11_05_000202_create_student_exam_results_table.php`
- **Create** `database/migrations/2026_11_05_000203_create_student_exam_section_marks_table.php`
- **Create** `database/migrations/2026_11_05_000204_add_exam_and_revoke_to_student_certificates.php`
- **Create** `app/Models/BatchExam.php`, `app/Models/ExamSection.php`, `app/Models/StudentExamResult.php`, `app/Models/StudentExamSectionMark.php`
- **Modify** `app/Models/StudentCertificate.php` (relations + `scopeActive` + `isRevoked`)
- **Create** `app/Services/ExamGradingService.php`
- **Create** `app/Http/Controllers/ExamController.php`
- **Modify** `app/Http/Controllers/CertificateController.php` (issue-from-result, duplicate guard, revoke)
- **Modify** `routes/web.php` (exam routes, revoke route, student results route)
- **Create** `resources/views/exams/{index,form,show}.blade.php`
- **Create** `resources/views/exams/student-results.blade.php`
- **Modify** `resources/views/layouts/admin.blade.php` (Exams / My Results sidebar links)
- **Modify** `resources/views/certificates/index.blade.php` (revoke button + revoked marker)
- **Modify** `resources/views/certificates/print.blade.php` (REVOKED stamp)
- **Create** `tests/Feature/BatchExamTest.php`
- **Modify** `tests/Feature/CertificateTest.php` (issue-from-result, duplicate, revoke)

---

## Task 1: Exam + sections tables and models

**Files:**
- Create: `database/migrations/2026_11_05_000200_create_batch_exams_table.php`
- Create: `database/migrations/2026_11_05_000201_create_exam_sections_table.php`
- Create: `app/Models/BatchExam.php`
- Create: `app/Models/ExamSection.php`
- Test: `tests/Feature/BatchExamTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BatchExamTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchExam;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchExamTest extends TestCase
{
    use RefreshDatabase;

    private function batch(?User $teacher = null): Batch
    {
        $teacher ??= User::factory()->create(['role' => 'teacher']);
        $course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 30000,
            'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);

        return Batch::create(['name' => 'A1 Morning', 'course_id' => $course->id, 'teacher_id' => $teacher->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'ongoing', 'max_students' => 20]);
    }

    public function test_exam_seeds_four_named_sections_with_given_marks(): void
    {
        $batch = $this->batch();
        $exam = BatchExam::create(['batch_id' => $batch->id, 'name' => 'A1 Final',
            'type' => 'final', 'exam_date' => '2026-12-20', 'status' => 'scheduled']);

        $exam->seedDefaultSections([
            'Hören' => ['max' => 25, 'pass' => 15],
            'Lesen' => ['max' => 25, 'pass' => 15],
            'Schreiben' => ['max' => 25, 'pass' => 15],
            'Sprechen' => ['max' => 25, 'pass' => 15],
        ]);

        $sections = $exam->sections()->orderBy('sort')->get();
        $this->assertSame(['Hören', 'Lesen', 'Schreiben', 'Sprechen'], $sections->pluck('name')->all());
        $this->assertSame('25.00', $sections->first()->max_marks);
        $this->assertSame('15.00', $sections->first()->passing_marks);
        $this->assertSame($batch->id, $exam->batch->id);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_exam_seeds_four_named_sections_with_given_marks`
Expected: FAIL — `Class "App\Models\BatchExam" not found`.

- [ ] **Step 3: Create the batch_exams migration**

Create `database/migrations/2026_11_05_000200_create_batch_exams_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('final');
            $table->date('exam_date');
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
            $table->index(['batch_id', 'exam_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_exams');
    }
};
```

- [ ] **Step 4: Create the exam_sections migration**

Create `database/migrations/2026_11_05_000201_create_exam_sections_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_exam_id')->constrained('batch_exams')->cascadeOnDelete();
            $table->string('name', 40);
            $table->decimal('max_marks', 6, 2);
            $table->decimal('passing_marks', 6, 2);
            $table->unsignedTinyInteger('sort');
            $table->timestamps();
            $table->unique(['batch_exam_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sections');
    }
};
```

- [ ] **Step 5: Create the ExamSection model**

Create `app/Models/ExamSection.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSection extends Model
{
    protected $fillable = ['batch_exam_id', 'name', 'max_marks', 'passing_marks', 'sort'];

    protected function casts(): array
    {
        return ['max_marks' => 'decimal:2', 'passing_marks' => 'decimal:2'];
    }

    public function batchExam()
    {
        return $this->belongsTo(BatchExam::class);
    }
}
```

- [ ] **Step 6: Create the BatchExam model**

Create `app/Models/BatchExam.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchExam extends Model
{
    /** Fixed CEFR section names, in display order. */
    public const SECTIONS = ['Hören', 'Lesen', 'Schreiben', 'Sprechen'];

    protected $fillable = ['batch_id', 'name', 'type', 'exam_date', 'status'];

    protected function casts(): array
    {
        return ['exam_date' => 'date'];
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function sections()
    {
        return $this->hasMany(ExamSection::class)->orderBy('sort');
    }

    public function results()
    {
        return $this->hasMany(StudentExamResult::class);
    }

    /**
     * Create the four fixed sections for this exam.
     * $marks is keyed by section name => ['max' => x, 'pass' => y].
     */
    public function seedDefaultSections(array $marks): void
    {
        foreach (self::SECTIONS as $i => $name) {
            $this->sections()->create([
                'name' => $name,
                'max_marks' => $marks[$name]['max'],
                'passing_marks' => $marks[$name]['pass'],
                'sort' => $i + 1,
            ]);
        }
    }
}
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=test_exam_seeds_four_named_sections_with_given_marks`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_11_05_000200_create_batch_exams_table.php database/migrations/2026_11_05_000201_create_exam_sections_table.php app/Models/BatchExam.php app/Models/ExamSection.php tests/Feature/BatchExamTest.php
git commit -m "feat: batch_exams and exam_sections tables and models"
```

---

## Task 2: Result tables, certificate columns, and pass computation

**Files:**
- Create: `database/migrations/2026_11_05_000202_create_student_exam_results_table.php`
- Create: `database/migrations/2026_11_05_000203_create_student_exam_section_marks_table.php`
- Create: `database/migrations/2026_11_05_000204_add_exam_and_revoke_to_student_certificates.php`
- Create: `app/Models/StudentExamResult.php`
- Create: `app/Models/StudentExamSectionMark.php`
- Modify: `app/Models/StudentCertificate.php`
- Test: `tests/Feature/BatchExamTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/BatchExamTest.php` (add `use App\Models\StudentExamResult;` to the imports):

```php
private function examWithSections(Batch $batch): BatchExam
{
    $exam = BatchExam::create(['batch_id' => $batch->id, 'name' => 'A1 Final',
        'type' => 'final', 'exam_date' => '2026-12-20', 'status' => 'scheduled']);
    $exam->seedDefaultSections([
        'Hören' => ['max' => 25, 'pass' => 15], 'Lesen' => ['max' => 25, 'pass' => 15],
        'Schreiben' => ['max' => 25, 'pass' => 15], 'Sprechen' => ['max' => 25, 'pass' => 15],
    ]);

    return $exam->load('sections');
}

public function test_overall_pass_requires_every_section_passed(): void
{
    $batch = $this->batch();
    $exam = $this->examWithSections($batch);
    $student = User::factory()->create(['role' => 'student']);

    $result = StudentExamResult::create(['batch_exam_id' => $exam->id, 'student_id' => $student->id,
        'overall_passed' => false, 'graded_by' => null, 'graded_at' => now()]);

    foreach ($exam->sections as $i => $section) {
        // First section below its passing mark; the rest above.
        $marks = $i === 0 ? 10 : 20;
        $result->sectionMarks()->create(['exam_section_id' => $section->id,
            'marks_obtained' => $marks, 'passed' => $marks >= (float) $section->passing_marks]);
    }

    $this->assertFalse($result->fresh()->computeOverallPassed());

    // Raise the failing section above its passing mark → overall passes.
    $result->sectionMarks()->where('exam_section_id', $exam->sections->first()->id)
        ->update(['marks_obtained' => 18, 'passed' => true]);
    $this->assertTrue($result->fresh()->load('sectionMarks', 'batchExam.sections')->computeOverallPassed());
}

public function test_overall_pass_is_false_when_a_section_mark_is_missing(): void
{
    $batch = $this->batch();
    $exam = $this->examWithSections($batch);
    $student = User::factory()->create(['role' => 'student']);

    $result = StudentExamResult::create(['batch_exam_id' => $exam->id, 'student_id' => $student->id,
        'overall_passed' => false, 'graded_at' => now()]);
    // Only three of four sections marked (all passing).
    foreach ($exam->sections->take(3) as $section) {
        $result->sectionMarks()->create(['exam_section_id' => $section->id, 'marks_obtained' => 20, 'passed' => true]);
    }

    $this->assertFalse($result->fresh()->load('sectionMarks', 'batchExam.sections')->computeOverallPassed());
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_overall_pass_requires_every_section_passed`
Expected: FAIL — `Class "App\Models\StudentExamResult" not found`.

- [ ] **Step 3: Create the student_exam_results migration**

Create `database/migrations/2026_11_05_000202_create_student_exam_results_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_exam_id')->constrained('batch_exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->boolean('overall_passed')->default(false);
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at');
            $table->timestamps();
            $table->unique(['batch_exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exam_results');
    }
};
```

- [ ] **Step 4: Create the student_exam_section_marks migration**

Create `database/migrations/2026_11_05_000203_create_student_exam_section_marks_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_exam_section_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_exam_result_id')->constrained('student_exam_results')->cascadeOnDelete();
            $table->foreignId('exam_section_id')->constrained('exam_sections')->restrictOnDelete();
            $table->decimal('marks_obtained', 6, 2);
            $table->boolean('passed')->default(false);
            $table->timestamps();
            $table->unique(['student_exam_result_id', 'exam_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exam_section_marks');
    }
};
```

- [ ] **Step 5: Create the certificate columns migration**

Create `database/migrations/2026_11_05_000204_add_exam_and_revoke_to_student_certificates.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_certificates', function (Blueprint $table) {
            $table->foreignId('student_exam_result_id')->nullable()->after('student_id')
                ->constrained('student_exam_results')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->after('issued_at');
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')
                ->constrained('users')->nullOnDelete();
            $table->string('revoke_reason', 1000)->nullable()->after('revoked_by');
        });
    }

    public function down(): void
    {
        Schema::table('student_certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_exam_result_id');
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['revoked_at', 'revoke_reason']);
        });
    }
};
```

- [ ] **Step 6: Create the StudentExamSectionMark model**

Create `app/Models/StudentExamSectionMark.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentExamSectionMark extends Model
{
    protected $fillable = ['student_exam_result_id', 'exam_section_id', 'marks_obtained', 'passed'];

    protected function casts(): array
    {
        return ['marks_obtained' => 'decimal:2', 'passed' => 'boolean'];
    }

    public function result()
    {
        return $this->belongsTo(StudentExamResult::class, 'student_exam_result_id');
    }

    public function section()
    {
        return $this->belongsTo(ExamSection::class, 'exam_section_id');
    }
}
```

- [ ] **Step 7: Create the StudentExamResult model**

Create `app/Models/StudentExamResult.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentExamResult extends Model
{
    protected $fillable = ['batch_exam_id', 'student_id', 'overall_passed', 'graded_by', 'graded_at'];

    protected function casts(): array
    {
        return ['overall_passed' => 'boolean', 'graded_at' => 'datetime'];
    }

    public function batchExam()
    {
        return $this->belongsTo(BatchExam::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function sectionMarks()
    {
        return $this->hasMany(StudentExamSectionMark::class);
    }

    public function certificate()
    {
        return $this->hasOne(StudentCertificate::class, 'student_exam_result_id');
    }

    /**
     * True only when every one of the exam's sections has a recorded,
     * passing mark. A missing section counts as not passed.
     */
    public function computeOverallPassed(): bool
    {
        $sections = $this->batchExam->sections;
        if ($sections->isEmpty()) {
            return false;
        }

        $marks = $this->sectionMarks->keyBy('exam_section_id');
        foreach ($sections as $section) {
            $mark = $marks->get($section->id);
            if (! $mark || ! $mark->passed) {
                return false;
            }
        }

        return true;
    }
}
```

- [ ] **Step 8: Add relations and helpers to StudentCertificate**

In `app/Models/StudentCertificate.php`, add `student_exam_result_id`, `revoked_at`, `revoked_by`, `revoke_reason` to `$fillable`; add `'revoked_at' => 'datetime'` to the casts array; and add these methods alongside the existing `student()`/`issuer()`:

```php
    public function result()
    {
        return $this->belongsTo(StudentExamResult::class, 'student_exam_result_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
```

- [ ] **Step 9: Run the tests to verify they pass**

Run: `php artisan test --filter=BatchExamTest`
Expected: all three BatchExamTest methods PASS.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_11_05_000202_create_student_exam_results_table.php database/migrations/2026_11_05_000203_create_student_exam_section_marks_table.php database/migrations/2026_11_05_000204_add_exam_and_revoke_to_student_certificates.php app/Models/StudentExamResult.php app/Models/StudentExamSectionMark.php app/Models/StudentCertificate.php tests/Feature/BatchExamTest.php
git commit -m "feat: exam result tables, certificate link/revoke columns, pass computation"
```

---

## Task 3: Grading service (save, validate, audit, correction guard)

**Files:**
- Create: `app/Services/ExamGradingService.php`
- Test: `tests/Feature/BatchExamTest.php`

**Notes:** This service is the only writer of results. It validates each mark is within `0..max_marks`, computes per-section `passed` and the overall, persists in one transaction, writes an audit row, and enforces the correction guard: an edit that would flip a result from passed to not-passed is rejected while a non-revoked certificate is linked. The guard reads the certificate columns added in Task 2; the issue/revoke behaviour arrives in Tasks 7–8.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/BatchExamTest.php` (add `use App\Models\AuditLog;`, `use App\Models\StudentCertificate;`, `use App\Services\ExamGradingService;`, `use Illuminate\Validation\ValidationException;` to the imports):

```php
/** @return array<int,float> section_id => marks */
private function marksFor(BatchExam $exam, float $value): array
{
    return $exam->sections->mapWithKeys(fn ($s) => [$s->id => $value])->all();
}

public function test_service_saves_marks_computes_pass_and_audits(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $batch = $this->batch();
    $exam = $this->examWithSections($batch);
    $student = User::factory()->create(['role' => 'student']);

    $result = app(ExamGradingService::class)->saveStudentResult($exam, $student->id, $this->marksFor($exam, 20), $admin);

    $this->assertTrue($result->overall_passed);
    $this->assertSame(4, $result->sectionMarks()->count());
    $this->assertSame(4, $result->sectionMarks()->where('passed', true)->count());
    $this->assertSame($admin->id, $result->graded_by);
    $this->assertSame(1, AuditLog::where('action', 'exam.result_graded')
        ->where('subject_type', 'student_exam_result')->where('subject_id', $result->id)->count());
}

public function test_service_rejects_marks_outside_range(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $batch = $this->batch();
    $exam = $this->examWithSections($batch);
    $student = User::factory()->create(['role' => 'student']);

    $over = $this->marksFor($exam, 20);
    $over[$exam->sections->first()->id] = 999; // above max 25

    $this->expectException(ValidationException::class);
    app(ExamGradingService::class)->saveStudentResult($exam, $student->id, $over, $admin);
}

public function test_service_is_idempotent_and_updates_on_resave(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $batch = $this->batch();
    $exam = $this->examWithSections($batch);
    $student = User::factory()->create(['role' => 'student']);
    $svc = app(ExamGradingService::class);

    $svc->saveStudentResult($exam, $student->id, $this->marksFor($exam, 20), $admin);
    $svc->saveStudentResult($exam, $student->id, $this->marksFor($exam, 22), $admin);

    $this->assertSame(1, StudentExamResult::where('batch_exam_id', $exam->id)->where('student_id', $student->id)->count());
    $result = StudentExamResult::where('batch_exam_id', $exam->id)->where('student_id', $student->id)->first();
    $this->assertSame('22.00', $result->sectionMarks->first()->marks_obtained);
}

public function test_service_blocks_pass_to_fail_edit_while_certificate_is_live(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $batch = $this->batch();
    $exam = $this->examWithSections($batch);
    $student = User::factory()->create(['role' => 'student']);
    $svc = app(ExamGradingService::class);

    $result = $svc->saveStudentResult($exam, $student->id, $this->marksFor($exam, 20), $admin);

    // A live (non-revoked) certificate is linked to the passing result.
    StudentCertificate::create(['student_id' => $student->id, 'student_exam_result_id' => $result->id,
        'student_name' => 'X', 'level' => 'A1', 'completion_date' => '2026-12-20',
        'certificate_number' => 'LGL-A1-2026-050', 'issued_by' => $admin->id, 'issued_at' => now()]);

    // Dropping one section below passing would flip overall to fail → blocked.
    $fail = $this->marksFor($exam, 20);
    $fail[$exam->sections->first()->id] = 5;

    try {
        $svc->saveStudentResult($exam, $student->id, $fail, $admin);
        $this->fail('Expected a validation exception.');
    } catch (ValidationException $e) {
        $this->assertStringContainsString('LGL-A1-2026-050', $e->getMessage());
    }

    $this->assertTrue($result->fresh()->overall_passed, 'Result must be unchanged after a blocked edit.');
}

public function test_service_allows_pass_to_fail_edit_after_certificate_revoked(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $batch = $this->batch();
    $exam = $this->examWithSections($batch);
    $student = User::factory()->create(['role' => 'student']);
    $svc = app(ExamGradingService::class);

    $result = $svc->saveStudentResult($exam, $student->id, $this->marksFor($exam, 20), $admin);
    StudentCertificate::create(['student_id' => $student->id, 'student_exam_result_id' => $result->id,
        'student_name' => 'X', 'level' => 'A1', 'completion_date' => '2026-12-20',
        'certificate_number' => 'LGL-A1-2026-051', 'issued_by' => $admin->id, 'issued_at' => now(),
        'revoked_at' => now(), 'revoked_by' => $admin->id, 'revoke_reason' => 'result correction']);

    $fail = $this->marksFor($exam, 20);
    $fail[$exam->sections->first()->id] = 5;
    $svc->saveStudentResult($exam, $student->id, $fail, $admin);

    $this->assertFalse($result->fresh()->overall_passed);
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=test_service_saves_marks_computes_pass_and_audits`
Expected: FAIL — `Class "App\Services\ExamGradingService" not found`.

- [ ] **Step 3: Create the service**

Create `app/Services/ExamGradingService.php`:

```php
<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BatchExam;
use App\Models\StudentExamResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamGradingService
{
    /**
     * Record (or update) one student's section marks for an exam.
     *
     * @param  array<int,float|string>  $sectionMarks  exam_section_id => marks_obtained
     */
    public function saveStudentResult(BatchExam $exam, int $studentId, array $sectionMarks, User $actor): StudentExamResult
    {
        $exam->loadMissing('sections');

        // Validate: every section present, numeric, within 0..max_marks.
        foreach ($exam->sections as $section) {
            if (! array_key_exists($section->id, $sectionMarks)) {
                throw ValidationException::withMessages([
                    'marks' => "Marks for {$section->name} are required.",
                ]);
            }
            $value = $sectionMarks[$section->id];
            if (! is_numeric($value) || $value < 0 || $value > (float) $section->max_marks) {
                throw ValidationException::withMessages([
                    "marks.{$section->id}" => "{$section->name}: marks must be between 0 and {$section->max_marks}.",
                ]);
            }
        }

        // Compute the new overall before writing, for the correction guard.
        $newOverall = true;
        foreach ($exam->sections as $section) {
            if ((float) $sectionMarks[$section->id] < (float) $section->passing_marks) {
                $newOverall = false;
                break;
            }
        }

        $existing = StudentExamResult::where('batch_exam_id', $exam->id)
            ->where('student_id', $studentId)->first();

        // Correction guard: a pass→fail flip is blocked while a live certificate is linked.
        if ($existing && $existing->overall_passed && ! $newOverall) {
            $liveCert = $existing->certificate()->whereNull('revoked_at')->first();
            if ($liveCert) {
                throw ValidationException::withMessages([
                    'marks' => "This result has certificate {$liveCert->certificate_number}. Revoke it before lowering the student below a pass.",
                ]);
            }
        }

        return DB::transaction(function () use ($exam, $studentId, $sectionMarks, $actor, $newOverall) {
            $result = StudentExamResult::updateOrCreate(
                ['batch_exam_id' => $exam->id, 'student_id' => $studentId],
                ['overall_passed' => $newOverall, 'graded_by' => $actor->id, 'graded_at' => now()],
            );

            foreach ($exam->sections as $section) {
                $marks = $sectionMarks[$section->id];
                $result->sectionMarks()->updateOrCreate(
                    ['exam_section_id' => $section->id],
                    ['marks_obtained' => $marks, 'passed' => (float) $marks >= (float) $section->passing_marks],
                );
            }

            AuditLog::create(['actor_id' => $actor->id, 'action' => 'exam.result_graded',
                'subject_type' => 'student_exam_result', 'subject_id' => $result->id, 'changes' => []]);

            return $result;
        });
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=BatchExamTest`
Expected: all BatchExamTest methods PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ExamGradingService.php tests/Feature/BatchExamTest.php
git commit -m "feat: exam grading service with validation, audit and correction guard"
```

---

## Task 4: Exam controller and routes (CRUD + result entry)

**Files:**
- Create: `app/Http/Controllers/ExamController.php`
- Modify: `routes/web.php`
- Create (placeholder) `resources/views/exams/{index,form,show}.blade.php` (replaced in Task 5)
- Test: `tests/Feature/BatchExamTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/BatchExamTest.php` (add `use App\Models\Enrollment;` to the imports):

```php
private function enroll(Batch $batch, User $student): Enrollment
{
    return Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id,
        'enrollment_date' => '2026-10-01', 'total_fee' => 30000, 'status' => 'active']);
}

public function test_admin_creates_an_exam_with_section_marks(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $batch = $this->batch();

    $this->actingAs($admin)->post('/exams', [
        'batch_id' => $batch->id, 'name' => 'A1 Final', 'type' => 'final', 'exam_date' => '2026-12-20',
        'sections' => [
            ['name' => 'Hören', 'max_marks' => 25, 'passing_marks' => 15],
            ['name' => 'Lesen', 'max_marks' => 25, 'passing_marks' => 15],
            ['name' => 'Schreiben', 'max_marks' => 30, 'passing_marks' => 18],
            ['name' => 'Sprechen', 'max_marks' => 20, 'passing_marks' => 12],
        ],
    ])->assertRedirect();

    $exam = BatchExam::sole();
    $this->assertSame(4, $exam->sections()->count());
    $this->assertSame('30.00', $exam->sections()->where('name', 'Schreiben')->value('max_marks'));
}

public function test_teacher_cannot_create_an_exam(): void
{
    $teacher = User::factory()->create(['role' => 'teacher']);
    $batch = $this->batch($teacher);

    $this->actingAs($teacher)->post('/exams', ['batch_id' => $batch->id, 'name' => 'X',
        'type' => 'final', 'exam_date' => '2026-12-20', 'sections' => []])->assertForbidden();
}

public function test_teacher_grades_own_batch_but_not_another(): void
{
    $owner = User::factory()->create(['role' => 'teacher']);
    $other = User::factory()->create(['role' => 'teacher']);
    $batch = $this->batch($owner);
    $exam = $this->examWithSections($batch);
    $student = User::factory()->create(['role' => 'student']);
    $this->enroll($batch, $student);

    $payload = ['student_id' => $student->id, 'marks' => $this->marksFor($exam, 20)];

    $this->actingAs($other)->post("/exams/{$exam->id}/results", $payload)->assertForbidden();
    $this->actingAs($owner)->post("/exams/{$exam->id}/results", $payload)->assertRedirect();
    $this->assertTrue(StudentExamResult::sole()->overall_passed);
}

public function test_only_batch_enrollees_can_be_graded(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $batch = $this->batch();
    $exam = $this->examWithSections($batch);
    $outsider = User::factory()->create(['role' => 'student']); // not enrolled

    $this->actingAs($admin)->post("/exams/{$exam->id}/results",
        ['student_id' => $outsider->id, 'marks' => $this->marksFor($exam, 20)])
        ->assertSessionHasErrors('student_id');
    $this->assertSame(0, StudentExamResult::count());
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=test_admin_creates_an_exam_with_section_marks`
Expected: FAIL (route `/exams` returns 404).

- [ ] **Step 3: Create placeholder views (so controller actions render)**

Create `resources/views/exams/index.blade.php`:

```blade
@extends('layouts.admin')
@section('title', 'Exams')
@section('content')<div>Exams</div>@endsection
```

Create `resources/views/exams/form.blade.php`:

```blade
@extends('layouts.admin')
@section('title', 'Exam')
@section('content')<form method="post" action="{{ route('exams.store') }}">@csrf</form>@endsection
```

Create `resources/views/exams/show.blade.php`:

```blade
@extends('layouts.admin')
@section('title', 'Exam')
@section('content')<div>{{ $exam->name }}</div>@endsection
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/ExamController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchExam;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\ExamGradingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher');
        // Exam setup is Admin only; result entry is shared.
        $this->middleware('role:admin')->only(['create', 'store', 'edit', 'update']);
    }

    public function index(Request $request)
    {
        $exams = BatchExam::query()
            ->with('batch.course:id,name,level')
            ->when(auth()->user()->isTeacher(),
                fn ($q) => $q->whereHas('batch', fn ($b) => $b->where('teacher_id', auth()->id())))
            ->when($request->filled('batch'), fn ($q) => $q->where('batch_id', (int) $request->query('batch')))
            ->orderByDesc('exam_date')->get();

        return view('exams.index', compact('exams'));
    }

    public function create()
    {
        $batches = Batch::with('course:id,name,level')->orderBy('name')->get();
        $sectionNames = BatchExam::SECTIONS;

        return view('exams.form', ['exam' => null, 'batches' => $batches, 'sectionNames' => $sectionNames]);
    }

    public function store(Request $request)
    {
        $data = $this->validateExam($request);

        $exam = BatchExam::create([
            'batch_id' => $data['batch_id'], 'name' => $data['name'],
            'type' => $data['type'], 'exam_date' => $data['exam_date'], 'status' => 'scheduled',
        ]);
        foreach ($data['sections'] as $i => $s) {
            $exam->sections()->create(['name' => $s['name'], 'max_marks' => $s['max_marks'],
                'passing_marks' => $s['passing_marks'], 'sort' => $i + 1]);
        }

        return redirect()->route('exams.show', $exam)->with('success', 'Exam created.');
    }

    public function edit(BatchExam $exam)
    {
        $batches = Batch::with('course:id,name,level')->orderBy('name')->get();
        $exam->load('sections');

        return view('exams.form', ['exam' => $exam, 'batches' => $batches, 'sectionNames' => BatchExam::SECTIONS]);
    }

    public function update(Request $request, BatchExam $exam)
    {
        $data = $this->validateExam($request);

        $exam->update(['batch_id' => $data['batch_id'], 'name' => $data['name'],
            'type' => $data['type'], 'exam_date' => $data['exam_date']]);
        foreach ($data['sections'] as $i => $s) {
            $exam->sections()->updateOrCreate(['name' => $s['name']],
                ['max_marks' => $s['max_marks'], 'passing_marks' => $s['passing_marks'], 'sort' => $i + 1]);
        }

        return redirect()->route('exams.show', $exam)->with('success', 'Exam updated.');
    }

    public function show(BatchExam $exam)
    {
        $exam->load(['batch.course', 'sections', 'results.sectionMarks', 'results.certificate']);
        $this->authorizeTeacher($exam);

        $students = User::students()
            ->whereHas('enrollments', fn ($q) => $q->where('batch_id', $exam->batch_id)
                ->whereIn('status', ['active', 'completed']))
            ->orderBy('name')->get(['id', 'name']);

        $resultsByStudent = $exam->results->keyBy('student_id');

        return view('exams.show', compact('exam', 'students', 'resultsByStudent'));
    }

    public function saveResults(Request $request, BatchExam $exam, ExamGradingService $service)
    {
        $exam->load('sections');
        $this->authorizeTeacher($exam);

        $validated = $request->validate([
            'student_id' => ['required', Rule::exists('enrollments', 'student_id')
                ->where('batch_id', $exam->batch_id)->whereIn('status', ['active', 'completed'])],
            'marks' => ['required', 'array'],
        ]);

        $marks = collect($validated['marks'])->mapWithKeys(fn ($v, $k) => [(int) $k => $v])->all();
        $service->saveStudentResult($exam, (int) $validated['student_id'], $marks, $request->user());

        return back()->with('success', 'Result saved.');
    }

    private function validateExam(Request $request): array
    {
        return $request->validate([
            'batch_id' => ['required', Rule::exists('batches', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['final', 'mid', 'retake', 'other'])],
            'exam_date' => ['required', 'date'],
            'sections' => ['required', 'array', 'size:4'],
            'sections.*.name' => ['required', Rule::in(BatchExam::SECTIONS)],
            'sections.*.max_marks' => ['required', 'numeric', 'min:1'],
            'sections.*.passing_marks' => ['required', 'numeric', 'min:0', 'lte:sections.*.max_marks'],
        ]);
    }

    private function authorizeTeacher(BatchExam $exam): void
    {
        abort_if(auth()->user()->isTeacher() && $exam->batch->teacher_id !== auth()->id(), 403);
    }
}
```

Note: `sections.*.passing_marks` uses `lte:sections.*.max_marks`; if the installed Laravel validator does not resolve the wildcard sibling, replace that rule with `['required','numeric','min:0']` and add an explicit per-row check at the top of `validateExam` after `$request->validate(...)`:

```php
        foreach ($request->input('sections', []) as $i => $s) {
            if (isset($s['max_marks'], $s['passing_marks']) && $s['passing_marks'] > $s['max_marks']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "sections.$i.passing_marks" => 'Passing marks cannot exceed max marks.',
                ]);
            }
        }
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, immediately after the Certificate routes block (after the `certificates.print` route, ~line 215), add:

```php
    // Exam Routes
    Route::get('/exams', [App\Http\Controllers\ExamController::class, 'index'])
        ->name('exams.index')->middleware('role:admin,teacher');
    Route::get('/exams/create', [App\Http\Controllers\ExamController::class, 'create'])
        ->name('exams.create')->middleware('role:admin');
    Route::post('/exams', [App\Http\Controllers\ExamController::class, 'store'])
        ->name('exams.store')->middleware('role:admin');
    Route::get('/exams/{exam}/edit', [App\Http\Controllers\ExamController::class, 'edit'])
        ->name('exams.edit')->middleware('role:admin');
    Route::put('/exams/{exam}', [App\Http\Controllers\ExamController::class, 'update'])
        ->name('exams.update')->middleware('role:admin');
    Route::get('/exams/{exam}', [App\Http\Controllers\ExamController::class, 'show'])
        ->name('exams.show')->middleware('role:admin,teacher');
    Route::post('/exams/{exam}/results', [App\Http\Controllers\ExamController::class, 'saveResults'])
        ->name('exams.results.store')->middleware('role:admin,teacher');
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=BatchExamTest`
Expected: all BatchExamTest methods PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ExamController.php routes/web.php resources/views/exams/
git commit -m "feat: exam controller, routes and result entry (admin + own-batch teacher)"
```

---

## Task 5: Exam views (index, form, show grid) and sidebar

**Files:**
- Modify: `resources/views/exams/index.blade.php`
- Modify: `resources/views/exams/form.blade.php`
- Modify: `resources/views/exams/show.blade.php`
- Modify: `resources/views/layouts/admin.blade.php`
- Test: `tests/Feature/BatchExamTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/BatchExamTest.php`:

```php
public function test_show_lists_enrollees_with_a_marks_grid_and_pass_state(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $batch = $this->batch();
    $exam = $this->examWithSections($batch);
    $student = User::factory()->create(['role' => 'student', 'name' => 'Sara Khan']);
    $this->enroll($batch, $student);
    app(ExamGradingService::class)->saveStudentResult($exam, $student->id, $this->marksFor($exam, 20), $admin);

    $this->actingAs($admin)->get("/exams/{$exam->id}")->assertOk()
        ->assertSee('Sara Khan')
        ->assertSee('Hören')
        ->assertSee('Passed');
}

public function test_sidebar_shows_exams_for_admin_and_teacher_not_student(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $teacher = User::factory()->create(['role' => 'teacher']);
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($admin)->get('/exams')->assertOk()->assertSee('Exams');
    $this->actingAs($teacher)->get('/exams')->assertOk();
    $this->actingAs($student)->get('/exams')->assertForbidden();
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_show_lists_enrollees_with_a_marks_grid_and_pass_state`
Expected: FAIL (placeholder show view lacks the student name / "Passed").

- [ ] **Step 3: Replace the exams index view**

Overwrite `resources/views/exams/index.blade.php`:

```blade
@extends('layouts.admin')
@section('title', 'Exams')
@section('content')
<div class="panel-heading d-flex justify-content-between align-items-center">
    <h1>Exams</h1>
    @if(auth()->user()->isAdmin())
        <a class="btn btn-primary" href="{{ route('exams.create') }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>New exam</a>
    @endif
</div>

<section class="panel">
    <div class="table-responsive"><table class="table list-table">
        <thead><tr><th scope="col">Exam</th><th scope="col">Batch</th><th scope="col">Level</th><th scope="col">Date</th><th scope="col">Type</th><th scope="col" class="d-print-none">&nbsp;</th></tr></thead>
        <tbody>
        @forelse($exams as $exam)
            <tr>
                <td class="cell-main">{{ $exam->name }}</td>
                <td>{{ $exam->batch->name }}</td>
                <td>{{ $exam->batch->course->level }}</td>
                <td>{{ $exam->exam_date->format('d M Y') }}</td>
                <td>{{ ucfirst($exam->type) }}</td>
                <td class="d-print-none"><a class="btn btn-outline-secondary btn-sm" href="{{ route('exams.show', $exam) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty-state">No exams yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
```

- [ ] **Step 4: Replace the exam form view**

Overwrite `resources/views/exams/form.blade.php`:

```blade
@extends('layouts.admin')
@section('title', $exam ? 'Edit exam' : 'New exam')
@section('content')
@php
    $action = $exam ? route('exams.update', $exam) : route('exams.store');
    $sections = $exam ? $exam->sections : collect($sectionNames)->map(fn ($n) => (object) ['name' => $n, 'max_marks' => 25, 'passing_marks' => 15]);
@endphp
<div class="panel-heading"><h1>{{ $exam ? 'Edit exam' : 'New exam' }}</h1></div>

<section class="panel" style="padding:18px 22px;max-width:760px">
    <form method="post" action="{{ $action }}">
        @csrf
        @if($exam)@method('PUT')@endif

        <div class="mb-3">
            <label class="form-label" for="batch_id">Batch</label>
            <select class="form-select" id="batch_id" name="batch_id" required>
                <option value="">— Select a batch —</option>
                @foreach($batches as $b)
                    <option value="{{ $b->id }}" @selected((int) old('batch_id', $exam->batch_id ?? 0) === $b->id)>{{ $b->name }} ({{ $b->course->level }})</option>
                @endforeach
            </select>
            @error('batch_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="row">
            <div class="col-sm-6 mb-3">
                <label class="form-label" for="name">Exam name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $exam->name ?? '') }}" required maxlength="255">
                @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="type">Type</label>
                <select class="form-select" id="type" name="type" required>
                    @foreach(['final', 'mid', 'retake', 'other'] as $t)
                        <option value="{{ $t }}" @selected(old('type', $exam->type ?? 'final') === $t)>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="exam_date">Date</label>
                <input class="form-control" id="exam_date" name="exam_date" type="date"
                       value="{{ old('exam_date', isset($exam) ? $exam->exam_date->toDateString() : now()->toDateString()) }}" required>
                @error('exam_date')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>

        <h2 class="h6 mt-2">Sections</h2>
        @error('sections')<div class="text-danger small">{{ $message }}</div>@enderror
        <div class="table-responsive"><table class="table">
            <thead><tr><th scope="col">Section</th><th scope="col">Max marks</th><th scope="col">Passing marks</th></tr></thead>
            <tbody>
            @foreach($sections as $i => $s)
                <tr>
                    <td>
                        {{ $s->name }}
                        <input type="hidden" name="sections[{{ $i }}][name]" value="{{ $s->name }}">
                    </td>
                    <td><input class="form-control" type="number" step="0.01" min="1"
                               name="sections[{{ $i }}][max_marks]" value="{{ old("sections.$i.max_marks", $s->max_marks) }}" required></td>
                    <td><input class="form-control" type="number" step="0.01" min="0"
                               name="sections[{{ $i }}][passing_marks]" value="{{ old("sections.$i.passing_marks", $s->passing_marks) }}" required></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a class="btn btn-outline-secondary" href="{{ route('exams.index') }}">Cancel</a>
            <button class="btn btn-primary" type="submit">{{ $exam ? 'Save exam' : 'Create exam' }}</button>
        </div>
    </form>
</section>
@endsection
```

- [ ] **Step 5: Replace the exam show view**

Overwrite `resources/views/exams/show.blade.php`:

```blade
@extends('layouts.admin')
@section('title', $exam->name)
@section('content')
<div class="panel-heading d-flex justify-content-between align-items-center">
    <h1>{{ $exam->name }}</h1>
    @if(auth()->user()->isAdmin())
        <a class="btn btn-outline-secondary" href="{{ route('exams.edit', $exam) }}">Edit exam</a>
    @endif
</div>

<section class="panel" style="padding:14px 18px">
    <div>{{ $exam->batch->name }} &middot; Level {{ $exam->batch->course->level }} &middot; {{ $exam->exam_date->format('d M Y') }} &middot; {{ ucfirst($exam->type) }}</div>
</section>

<section class="panel mt-3">
    <div class="panel-heading"><h2 class="h5">Results</h2></div>
    <div class="table-responsive"><table class="table list-table align-middle">
        <thead><tr>
            <th scope="col">Student</th>
            @foreach($exam->sections as $section)
                <th scope="col">{{ $section->name }}<span class="cell-sub">pass {{ rtrim(rtrim($section->passing_marks, '0'), '.') }}/{{ rtrim(rtrim($section->max_marks, '0'), '.') }}</span></th>
            @endforeach
            <th scope="col">Overall</th>
            <th scope="col" class="d-print-none">&nbsp;</th>
        </tr></thead>
        <tbody>
        @forelse($students as $student)
            @php $result = $resultsByStudent->get($student->id); $marks = $result?->sectionMarks->keyBy('exam_section_id'); @endphp
            <tr>
                <form method="post" action="{{ route('exams.results.store', $exam) }}">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $student->id }}">
                    <td class="cell-main">{{ $student->name }}</td>
                    @foreach($exam->sections as $section)
                        @php $m = $marks?->get($section->id); @endphp
                        <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" max="{{ $section->max_marks }}"
                                   name="marks[{{ $section->id }}]" value="{{ $m?->marks_obtained }}" style="width:5.5rem"
                                   @class(['is-invalid' => $m && ! $m->passed])></td>
                    @endforeach
                    <td>
                        @if($result)
                            <span class="badge {{ $result->overall_passed ? 'bg-success' : 'bg-danger' }}">{{ $result->overall_passed ? 'Passed' : 'Failed' }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="d-print-none text-nowrap">
                        <button class="btn btn-primary btn-sm" type="submit">Save</button>
                        @if($result && $result->overall_passed && auth()->user()->isAdmin())
                            @if($result->certificate && ! $result->certificate->isRevoked())
                                <a class="btn btn-outline-success btn-sm" href="{{ route('certificates.print', $result->certificate) }}" target="_blank" rel="noopener">Cert {{ $result->certificate->certificate_number }}</a>
                            @else
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('certificates.index', ['result' => $result->id]) }}">Issue certificate</a>
                            @endif
                        @endif
                    </td>
                </form>
            </tr>
        @empty
            <tr><td colspan="{{ $exam->sections->count() + 3 }}" class="empty-state">No students enrolled in this batch.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
```

Note: a `<form>` wrapping `<tr>` children is not valid HTML table structure in every browser. If the row-form submit misbehaves during manual testing, switch each row to a cell-level form (wrap the inputs + Save button of one row in a single `<td>`-contained form, or give each input a `form="exam-row-{{ $student->id }}"` attribute and place the `<form id>` outside the table). The feature test only asserts rendered text, so it passes either way.

- [ ] **Step 6: Add the sidebar links**

In `resources/views/layouts/admin.blade.php`, find the admin-only "Certificates" sidebar `<li>` (added by the certificate branch, near the Reports links). Immediately after that `<li>`, add an Exams link visible to Admin and Teacher:

```blade
                        @if(auth()->user()->isAdmin() || auth()->user()->isTeacher())
                        <li class="nav-item"><a class="nav-link {{ request()->is('exams*') ? 'active' : '' }}" href="{{ route('exams.index') }}"><i class="fas fa-file-pen me-2" aria-hidden="true"></i>Exams</a></li>
                        @endif
```

Then find where student-only sidebar links live (the `@if(auth()->user()->isStudent())` block containing "My Attendance"/"My Fees"). Add a student results link inside that block:

```blade
                        <li class="nav-item"><a class="nav-link {{ request()->is('student/results*') ? 'active' : '' }}" href="{{ route('student.results') }}"><i class="fas fa-file-circle-check me-2" aria-hidden="true"></i>My Results</a></li>
```

(The `student.results` route is added in Task 6; this link renders only for students, and Task 6's test covers it. If you run the full suite between Task 5 and Task 6, add the route first — but committing Task 5 alone is fine because the link is inside the student block and student view tests run in Task 6.)

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter=BatchExamTest`
Expected: the two new view tests PASS (and the rest still pass).

- [ ] **Step 8: Commit**

```bash
git add resources/views/exams/ resources/views/layouts/admin.blade.php tests/Feature/BatchExamTest.php
git commit -m "feat: exam index, form, results grid views and sidebar links"
```

---

## Task 6: Student "My Results" page

**Files:**
- Modify: `app/Http/Controllers/ExamController.php` (add `studentResults`)
- Modify: `routes/web.php` (student route)
- Create: `resources/views/exams/student-results.blade.php`
- Test: `tests/Feature/BatchExamTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/BatchExamTest.php`:

```php
public function test_student_sees_own_results_only(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $batch = $this->batch();
    $exam = $this->examWithSections($batch);
    $mine = User::factory()->create(['role' => 'student', 'name' => 'Mine']);
    $other = User::factory()->create(['role' => 'student', 'name' => 'Other']);
    $this->enroll($batch, $mine);
    $this->enroll($batch, $other);
    $svc = app(ExamGradingService::class);
    $svc->saveStudentResult($exam, $mine->id, $this->marksFor($exam, 20), $admin);
    $svc->saveStudentResult($exam, $other->id, $this->marksFor($exam, 20), $admin);

    $this->actingAs($mine)->get('/student/results')->assertOk()
        ->assertSee($exam->name)
        ->assertSee('Passed')
        ->assertDontSee('Other');
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_student_sees_own_results_only`
Expected: FAIL (route `/student/results` returns 404).

- [ ] **Step 3: Add the controller action**

In `app/Http/Controllers/ExamController.php`, add:

```php
    public function studentResults(Request $request)
    {
        $results = \App\Models\StudentExamResult::where('student_id', $request->user()->id)
            ->with(['batchExam.batch.course:id,level', 'sectionMarks.section', 'certificate'])
            ->orderByDesc('graded_at')->get();

        return view('exams.student-results', compact('results'));
    }
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, inside the `Route::middleware('role:student')->group(function () { ... })` block (~line 230), add:

```php
        Route::get('/student/results', [App\Http\Controllers\ExamController::class, 'studentResults'])
            ->name('student.results');
```

- [ ] **Step 5: Create the view**

Create `resources/views/exams/student-results.blade.php`:

```blade
@extends('layouts.admin')
@section('title', 'My Results')
@section('content')
<div class="panel-heading"><h1>My Results</h1></div>

@forelse($results as $result)
    <section class="panel mt-3" style="padding:14px 18px">
        <div class="d-flex justify-content-between align-items-center">
            <div><strong>{{ $result->batchExam->name }}</strong><span class="cell-sub">{{ $result->batchExam->batch->name }} &middot; Level {{ $result->batchExam->batch->course->level }} &middot; {{ $result->batchExam->exam_date->format('d M Y') }}</span></div>
            <span class="badge {{ $result->overall_passed ? 'bg-success' : 'bg-danger' }}">{{ $result->overall_passed ? 'Passed' : 'Failed' }}</span>
        </div>
        <div class="table-responsive mt-2"><table class="table mb-0">
            <thead><tr><th scope="col">Section</th><th scope="col">Marks</th><th scope="col">Result</th></tr></thead>
            <tbody>
            @foreach($result->sectionMarks->sortBy('section.sort') as $mark)
                <tr>
                    <td>{{ $mark->section->name }}</td>
                    <td>{{ rtrim(rtrim($mark->marks_obtained, '0'), '.') }} / {{ rtrim(rtrim($mark->section->max_marks, '0'), '.') }}</td>
                    <td>{!! $mark->passed ? '<span class="badge bg-success">Pass</span>' : '<span class="badge bg-danger">Fail</span>' !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @if($result->certificate && ! $result->certificate->isRevoked())
            <a class="btn btn-outline-success btn-sm mt-2" href="{{ route('certificates.print', $result->certificate) }}" target="_blank" rel="noopener">View certificate</a>
        @endif
    </section>
@empty
    <section class="panel mt-3"><div class="empty-state">No exam results yet.</div></section>
@endforelse
@endsection
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=test_student_sees_own_results_only`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ExamController.php routes/web.php resources/views/exams/student-results.blade.php tests/Feature/BatchExamTest.php
git commit -m "feat: student My Results page"
```

---

## Task 7: Issue certificate from a passing result (link + duplicate guard)

**Files:**
- Modify: `app/Http/Controllers/CertificateController.php`
- Test: `tests/Feature/CertificateTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/CertificateTest.php` (add `use App\Models\Batch;`, `use App\Models\Course;`, `use App\Models\Enrollment;`, `use App\Models\BatchExam;`, `use App\Models\StudentExamResult;`, `use App\Services\ExamGradingService;` if not already imported):

```php
private function passingResult(User $admin, User $student, string $level = 'A1'): StudentExamResult
{
    $course = Course::create(['name' => 'German '.$level, 'level' => $level, 'total_fee' => 30000,
        'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);
    $batch = Batch::create(['name' => $level.' Morning', 'course_id' => $course->id, 'teacher_id' => $admin->id,
        'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'ongoing', 'max_students' => 20]);
    Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id,
        'enrollment_date' => '2026-10-01', 'total_fee' => 30000, 'status' => 'active']);
    $exam = BatchExam::create(['batch_id' => $batch->id, 'name' => $level.' Final', 'type' => 'final',
        'exam_date' => '2026-12-20', 'status' => 'scheduled']);
    $exam->seedDefaultSections(['Hören' => ['max' => 25, 'pass' => 15], 'Lesen' => ['max' => 25, 'pass' => 15],
        'Schreiben' => ['max' => 25, 'pass' => 15], 'Sprechen' => ['max' => 25, 'pass' => 15]]);
    $marks = $exam->load('sections')->sections->mapWithKeys(fn ($s) => [$s->id => 20])->all();

    return app(ExamGradingService::class)->saveStudentResult($exam, $student->id, $marks, $admin);
}

public function test_issuing_from_a_passing_result_links_and_prefills_level(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student', 'name' => 'Sara']);
    $result = $this->passingResult($admin, $student, 'B1');

    $response = $this->actingAs($admin)->post('/certificates', [
        'student_id' => $student->id, 'student_exam_result_id' => $result->id,
        'student_name' => 'Sara Khan', 'father_name' => 'Imran', 'level' => 'B1', 'completion_date' => '2026-12-20',
    ]);

    $cert = StudentCertificate::sole();
    $response->assertRedirect('/certificates/'.$cert->id.'/print');
    $this->assertSame($result->id, $cert->student_exam_result_id);
    $this->assertSame('B1', $cert->level);
}

public function test_cannot_issue_a_second_active_certificate_for_same_student_and_level(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student', 'name' => 'Sara']);

    StudentCertificate::create(['student_id' => $student->id, 'student_name' => 'Sara', 'level' => 'A1',
        'completion_date' => '2026-10-01', 'certificate_number' => 'LGL-A1-'.now()->year.'-001',
        'issued_by' => $admin->id, 'issued_at' => now()]);

    $this->actingAs($admin)->post('/certificates', ['student_id' => $student->id,
        'student_name' => 'Sara', 'level' => 'A1', 'completion_date' => '2026-10-02'])
        ->assertSessionHasErrors('student_id');

    $this->assertSame(1, StudentCertificate::count());
}

public function test_a_revoked_certificate_does_not_block_reissue(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student', 'name' => 'Sara']);

    StudentCertificate::create(['student_id' => $student->id, 'student_name' => 'Sara', 'level' => 'A1',
        'completion_date' => '2026-10-01', 'certificate_number' => 'LGL-A1-'.now()->year.'-001',
        'issued_by' => $admin->id, 'issued_at' => now(), 'revoked_at' => now(),
        'revoked_by' => $admin->id, 'revoke_reason' => 'error']);

    $this->actingAs($admin)->post('/certificates', ['student_id' => $student->id,
        'student_name' => 'Sara', 'level' => 'A1', 'completion_date' => '2026-10-02'])->assertRedirect();

    $this->assertSame(2, StudentCertificate::count());
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=test_issuing_from_a_passing_result_links_and_prefills_level`
Expected: FAIL (`student_exam_result_id` not stored; no duplicate guard).

- [ ] **Step 3: Update the store validation and creation**

In `app/Http/Controllers/CertificateController.php`, replace the `$data = $request->validate([...]);` block in `store()` with:

```php
        $data = $request->validate([
            'student_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'student')],
            'student_exam_result_id' => ['nullable', Rule::exists('student_exam_results', 'id')
                ->where('overall_passed', true)],
            'student_name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'level' => 'required|in:A1,A2,B1,B2,C1,C2',
            'completion_date' => 'required|date',
        ]);

        // One active (non-revoked) certificate per student + level.
        if (! empty($data['student_id'])) {
            $exists = StudentCertificate::active()
                ->where('student_id', $data['student_id'])->where('level', $data['level'])->exists();
            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'student_id' => 'This student already has an active '.$data['level'].' certificate. Revoke it first to reissue.',
                ]);
            }
        }
```

Then in the `StudentCertificate::create([...])` array inside the transaction, add after `'student_id' => $data['student_id'] ?? null,`:

```php
                        'student_exam_result_id' => $data['student_exam_result_id'] ?? null,
```

- [ ] **Step 4: Prefill the form from a result in `create()`**

In `CertificateController::create()`, after the existing `$preselect`/`$preselectId` lines, add result-based prefill (a passing result overrides the plain `?student=` prefill):

```php
        if ($request->filled('result')) {
            $result = StudentExamResult::with('batchExam.batch.course:id,level', 'student:id,name')
                ->where('overall_passed', true)->find((int) $request->query('result'));
            if ($result) {
                $preselect = [
                    'name' => $result->student?->name ?? '',
                    'level' => $result->batchExam?->batch?->course?->level ?? '',
                ];
                $preselectId = $result->student_id;
                $preselectResultId = $result->id;
            }
        }
        $preselectResultId ??= null;
```

And add `'preselectResultId'` to the `compact(...)` passed to the view:

```php
        return view('certificates.index', compact('students', 'studentMeta', 'preselect', 'preselectId', 'preselectResultId', 'certificates'));
```

Add the import at the top of the file: `use App\Models\StudentExamResult;`.

- [ ] **Step 5: Carry the result id through the form**

In `resources/views/certificates/index.blade.php`, inside the `<form method="post" action="{{ route('certificates.store') }}">`, right after `@csrf`, add:

```blade
        @if(! empty($preselectResultId))<input type="hidden" name="student_exam_result_id" value="{{ $preselectResultId }}">@endif
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=CertificateTest`
Expected: all CertificateTest methods PASS (existing + new).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/CertificateController.php resources/views/certificates/index.blade.php tests/Feature/CertificateTest.php
git commit -m "feat: issue certificate from a passing exam result with duplicate guard"
```

---

## Task 8: Certificate revocation (action, print stamp, list marker)

**Files:**
- Modify: `app/Http/Controllers/CertificateController.php` (add `revoke`)
- Modify: `routes/web.php` (revoke route)
- Modify: `resources/views/certificates/print.blade.php` (REVOKED stamp)
- Modify: `resources/views/certificates/index.blade.php` (revoke form + revoked marker)
- Test: `tests/Feature/CertificateTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/CertificateTest.php`:

```php
public function test_admin_revokes_a_certificate_with_a_reason(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);
    $cert = StudentCertificate::create(['student_id' => $student->id, 'student_name' => 'Sara', 'level' => 'A1',
        'completion_date' => '2026-10-01', 'certificate_number' => 'LGL-A1-'.now()->year.'-001',
        'issued_by' => $admin->id, 'issued_at' => now()]);

    $this->actingAs($admin)->post('/certificates/'.$cert->id.'/revoke', ['reason' => 'Marks corrected to a fail.'])
        ->assertRedirect();

    $cert->refresh();
    $this->assertTrue($cert->isRevoked());
    $this->assertSame($admin->id, $cert->revoked_by);
    $this->assertSame('Marks corrected to a fail.', $cert->revoke_reason);
    $this->assertSame(1, \App\Models\AuditLog::where('action', 'certificate.revoked')
        ->where('subject_id', $cert->id)->count());
}

public function test_revoke_requires_a_reason_and_rejects_double_revoke(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $cert = StudentCertificate::create(['student_name' => 'Sara', 'level' => 'A1',
        'completion_date' => '2026-10-01', 'certificate_number' => 'LGL-A1-'.now()->year.'-002',
        'issued_by' => $admin->id, 'issued_at' => now()]);

    $this->actingAs($admin)->post('/certificates/'.$cert->id.'/revoke', ['reason' => 'no'])
        ->assertSessionHasErrors('reason');

    $cert->update(['revoked_at' => now(), 'revoked_by' => $admin->id, 'revoke_reason' => 'already gone']);
    $this->actingAs($admin)->post('/certificates/'.$cert->id.'/revoke', ['reason' => 'A valid second reason here.'])
        ->assertSessionHasErrors('reason');
}

public function test_revoked_certificate_print_shows_the_revoked_stamp(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $cert = StudentCertificate::create(['student_name' => 'Sara', 'level' => 'A1',
        'completion_date' => '2026-10-01', 'certificate_number' => 'LGL-A1-'.now()->year.'-003',
        'issued_by' => $admin->id, 'issued_at' => now(), 'revoked_at' => now(),
        'revoked_by' => $admin->id, 'revoke_reason' => 'Issued in error.']);

    $this->actingAs($admin)->get('/certificates/'.$cert->id.'/print')->assertOk()
        ->assertSee('REVOKED')->assertSee('Issued in error.');
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=test_admin_revokes_a_certificate_with_a_reason`
Expected: FAIL (route `/certificates/{id}/revoke` returns 404).

- [ ] **Step 3: Add the `revoke` action**

In `app/Http/Controllers/CertificateController.php`, add:

```php
    public function revoke(Request $request, StudentCertificate $certificate)
    {
        if ($certificate->isRevoked()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reason' => 'This certificate is already revoked.',
            ]);
        }

        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);

        DB::transaction(function () use ($certificate, $data, $request) {
            $certificate->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id,
                'revoke_reason' => $data['reason']]);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'certificate.revoked',
                'subject_type' => 'student_certificate', 'subject_id' => $certificate->id, 'changes' => []]);
        });

        return back()->with('success', 'Certificate '.$certificate->certificate_number.' revoked.');
    }
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, after the `certificates.print` route (~line 215), add:

```php
    Route::post('/certificates/{certificate}/revoke', [App\Http\Controllers\CertificateController::class, 'revoke'])
        ->name('certificates.revoke')->middleware('role:admin');
```

- [ ] **Step 5: Add the REVOKED stamp to the print view**

In `resources/views/certificates/print.blade.php`, inside `.sheet` (after the `<div class="cert-number">...</div>` line), add:

```blade
        @if($cert->isRevoked())
            <div style="position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;pointer-events:none">
                <span style="border:6px solid #c0202a;color:#c0202a;font-size:54pt;font-weight:700;letter-spacing:6pt;padding:6mm 16mm;transform:rotate(-18deg);opacity:.75">REVOKED</span>
            </div>
            <div style="position:absolute;bottom:3mm;left:10mm;font-size:8pt;color:#c0202a">Revoked: {{ $cert->revoke_reason }}</div>
        @endif
```

- [ ] **Step 6: Add the revoke form + revoked marker to the issued list**

In `resources/views/certificates/index.blade.php`, in the issued-list row, replace the Reprint action cell with one that also marks revoked rows and offers a revoke dialog for active ones:

```blade
                <td class="d-print-none text-nowrap">
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('certificates.print', $certificate) }}" target="_blank" rel="noopener"><i class="fas fa-print me-1" aria-hidden="true"></i>Reprint</a>
                    @if($certificate->isRevoked())
                        <span class="badge bg-danger ms-1">Revoked</span>
                    @else
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#revoke-{{ $certificate->id }}">Revoke</button>
                    @endif
                </td>
```

And directly after that `<tr>...</tr>`, add a collapsible revoke form row (only for active certs):

```blade
            @unless($certificate->isRevoked())
            <tr class="collapse d-print-none" id="revoke-{{ $certificate->id }}">
                <td colspan="6">
                    <form method="post" action="{{ route('certificates.revoke', $certificate) }}" class="d-flex gap-2 align-items-start">
                        @csrf
                        <input class="form-control form-control-sm" name="reason" placeholder="Reason for revoking (min 5 chars)" maxlength="1000" required>
                        <button class="btn btn-danger btn-sm" type="submit">Confirm revoke</button>
                    </form>
                    @error('reason')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </td>
            </tr>
            @endunless
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter=CertificateTest`
Expected: all CertificateTest methods PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/CertificateController.php routes/web.php resources/views/certificates/ tests/Feature/CertificateTest.php
git commit -m "feat: certificate revocation with print stamp and list marker"
```

---

## Task 9: Full verification and documentation

**Files:**
- Modify: `docs/TASKS.md` (record E01 implementation)

- [ ] **Step 1: Run the whole exam + certificate suites**

Run: `php artisan test --filter=BatchExamTest` then `php artisan test --filter=CertificateTest`
Expected: all PASS.

- [ ] **Step 2: Run the full test suite (guard against regressions)**

Run: `php artisan test`
Expected: PASS, except the pre-existing, date-dependent `BatchScheduleTest::test_generate_from_schedule...` failure already documented in docs/TASKS.md (unrelated to this branch). Confirm no *other* test regressed.

- [ ] **Step 3: Lint the changed PHP**

Run: `vendor/bin/pint --dirty`
Expected: no outstanding style issues on changed files.

- [ ] **Step 4: Record the implementation in docs/TASKS.md**

Add a dated section to `docs/TASKS.md` under the existing entries, summarising: four new exam tables, section-wise pass (all four), Admin exam setup + own-batch-teacher result entry, student My Results, certificate issue-from-result with duplicate guard, revocation with print stamp, correction guard, legacy exam tables left dormant. Note the E01 clarification (passing rule = per-section, all sections required) is now resolved and implemented. State the test count from Step 2 and that no browser check / real-data migration was run (self-review only).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: verification and docs for batch exams (E01)"
```

---

## Self-Review (completed during planning)

- **Spec coverage:** data model (Tasks 1–2), pass computation all-sections (Tasks 2–3), grading service with validation + audit + correction guard (Task 3), exam CRUD + result entry + access (Task 4), views + sidebar (Task 5), student results (Task 6), issue-from-result + duplicate guard (Task 7), revocation + stamp (Task 8), access rules (Tasks 4–8), legacy tables untouched (new table names, Task 1), verification + docs (Task 9). All spec sections map to a task.
- **Placeholder scan:** no TBD/TODO; every code step shows full code. The two HTML/validation caveats (row-form structure in Task 5, wildcard `lte` in Task 4) include concrete fallbacks.
- **Type consistency:** `BatchExam::SECTIONS`, `seedDefaultSections(array)`, `computeOverallPassed(): bool`, `ExamGradingService::saveStudentResult(BatchExam, int, array, User): StudentExamResult`, `StudentCertificate::scopeActive`/`isRevoked()`, columns `student_exam_result_id`/`revoked_at`/`revoked_by`/`revoke_reason`, and route names (`exams.index`/`create`/`store`/`edit`/`update`/`show`/`results.store`, `student.results`, `certificates.revoke`) are used identically across tasks.
- **Out of scope confirmed:** no weighting rule, no custom section names, no auto-issue, no PDF package, no notifications/retake limits; legacy `exams`/`exam_results` dormant.
