<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\LevelMaterial;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LevelMaterialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Course $level;

    private Course $otherLevel;

    private Batch $batch;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true, 'salary_type' => 'monthly', 'monthly_salary' => 1000]);
        $this->level = Course::create(['name' => 'German', 'level' => 'A1', 'total_fee' => 9000, 'teacher_per_student_amount' => 500, 'duration_weeks' => 12]);
        $this->otherLevel = Course::create(['name' => 'German', 'level' => 'B1', 'total_fee' => 9000, 'teacher_per_student_amount' => 500, 'duration_weeks' => 12]);
        $this->batch = Batch::create(['course_id' => $this->level->id, 'teacher_id' => $this->teacher->id, 'name' => 'Morning A1',
            'start_date' => today(), 'end_date' => today()->addMonth(), 'status' => 'ongoing', 'max_students' => 10, 'meeting_platform' => 'in_person']);
    }

    private function upload(Course $level, string $title = 'Netzwerk A1 Book'): LevelMaterial
    {
        $this->actingAs($this->admin)->post(route('materials.store', $level), [
            'title' => $title, 'file' => UploadedFile::fake()->create('book.pdf', 200, 'application/pdf'),
        ])->assertRedirect(route('courses.show', $level))->assertSessionHasNoErrors();

        return LevelMaterial::latest('id')->firstOrFail();
    }

    private function student(Batch $batch, string $status): User
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $enrollment = app(EnrollmentService::class)->enrollStudent($student->id, $batch->id, 0, 1);
        $enrollment->forceFill(['status' => $status])->save();

        return $student;
    }

    public function test_admin_uploads_lists_and_deletes_material_on_level_page(): void
    {
        $material = $this->upload($this->level);
        Storage::disk('local')->assertExists($material->path);
        $this->assertStringStartsWith('level-materials/'.$this->level->id.'/', $material->path);
        $this->assertSame('book.pdf', $material->original_name);

        $this->get(route('courses.show', $this->level))->assertOk()->assertSee('Books &amp; Materials', false)->assertSee('Netzwerk A1 Book');
        $this->get(route('materials.download', $material))->assertOk()->assertDownload('book.pdf');

        $this->delete(route('materials.destroy', $material))->assertRedirect(route('courses.show', $this->level));
        $this->assertDatabaseMissing('level_materials', ['id' => $material->id]);
        Storage::disk('local')->assertMissing($material->path);
    }

    public function test_upload_validation_rejects_bad_type_size_and_missing_title(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('materials.store', $this->level), ['title' => 'Script', 'file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')])
            ->assertSessionHasErrors('file');
        $this->post(route('materials.store', $this->level), ['title' => 'Huge', 'file' => UploadedFile::fake()->create('big.pdf', LevelMaterial::MAX_KB + 1, 'application/pdf')])
            ->assertSessionHasErrors('file');
        $this->post(route('materials.store', $this->level), ['file' => UploadedFile::fake()->create('ok.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('title');
        $this->post(route('materials.store', $this->level), ['title' => 'Slides', 'file' => UploadedFile::fake()->create('deck.pptx', 10,
            'application/vnd.openxmlformats-officedocument.presentationml.presentation')])->assertSessionHasNoErrors();
        $this->assertSame(1, LevelMaterial::count());
    }

    public function test_only_admin_can_upload_or_delete(): void
    {
        $material = $this->upload($this->level);
        $student = $this->student($this->batch, 'active');
        foreach ([$this->teacher, $student] as $user) {
            $this->actingAs($user)->post(route('materials.store', $this->level), ['title' => 'X',
                'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertForbidden();
            $this->actingAs($user)->delete(route('materials.destroy', $material))->assertForbidden();
        }
        $this->assertSame(1, LevelMaterial::count());
    }

    public function test_teacher_sees_only_levels_of_assigned_batches(): void
    {
        $mine = $this->upload($this->level, 'A1 Book');
        $other = $this->upload($this->otherLevel, 'B1 Book');
        $stranger = User::factory()->create(['role' => 'teacher', 'is_active' => true, 'salary_type' => 'monthly', 'monthly_salary' => 1000]);

        $this->actingAs($this->teacher)->get(route('materials.index'))->assertOk()->assertSee('A1 Book')->assertDontSee('B1 Book');
        $this->get(route('materials.view', $mine))->assertOk()->assertHeader('content-disposition', 'inline; filename=book.pdf');
        $this->get(route('materials.download', $other))->assertForbidden();
        $this->actingAs($stranger)->get(route('materials.download', $mine))->assertForbidden();
        $this->actingAs($stranger)->get(route('materials.index'))->assertOk()->assertDontSee('A1 Book');
    }

    public function test_students_with_active_or_completed_enrollment_only(): void
    {
        $material = $this->upload($this->level, 'A1 Book');
        $this->upload($this->otherLevel, 'B1 Book');

        foreach (['active', 'completed'] as $status) {
            $student = $this->student($this->batch, $status);
            $this->actingAs($student)->get(route('materials.index'))->assertOk()->assertSee('A1 Book')->assertDontSee('B1 Book');
            $this->get(route('materials.view', $material))->assertOk();
            $this->get(route('materials.download', $material))->assertOk()->assertDownload('book.pdf');
        }
        foreach (['dropped', 'transferred'] as $status) {
            $student = $this->student($this->batch, $status);
            $this->actingAs($student)->get(route('materials.index'))->assertOk()->assertDontSee('A1 Book');
            $this->get(route('materials.download', $material))->assertForbidden();
            $this->get(route('materials.view', $material))->assertForbidden();
        }
    }

    public function test_non_previewable_files_download_and_level_rows_replace_sidebar_link(): void
    {
        $this->actingAs($this->admin)->post(route('materials.store', $this->level), ['title' => 'Notes',
            'file' => UploadedFile::fake()->create('notes.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]);
        $material = LevelMaterial::sole();
        $student = $this->student($this->batch, 'active');

        $this->actingAs($student)->get(route('materials.view', $material))->assertOk()->assertDownload('notes.docx');
        $this->get(route('student.dashboard'))->assertOk()->assertSee('1 attachment')
            ->assertSee(route('materials.index', ['course_id' => $this->level->id]), false)
            ->assertDontSee('Books &amp; Materials', false);
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()->assertDontSee(route('materials.index'), false);
        $this->get(route('courses.index'))->assertOk()->assertSee('1 attachment')
            ->assertSee(route('materials.index', ['course_id' => $this->level->id]), false);
        $this->get(route('materials.index', ['course_id' => $this->otherLevel->id]))->assertOk()->assertDontSee('Notes');
    }
}
