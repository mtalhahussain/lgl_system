<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PeopleAndCourseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    private function personData(string $email): array
    {
        return ['name' => 'New Person', 'email' => $email, 'phone' => '03001234567',
            'address' => 'Karachi', 'password' => 'strong-password', 'password_confirmation' => 'strong-password', 'is_active' => 1];
    }

    public function test_admin_can_create_edit_and_search_a_student_without_fake_database_fields(): void
    {
        $this->get('/students/create')->assertOk()->assertSee('Password');
        $this->post('/students', $this->personData('new.student@lgl.com') + ['role' => 'admin'])->assertRedirect('/students');
        $student = User::where('email', 'new.student@lgl.com')->sole();
        $this->assertSame('student', $student->role);
        $this->assertTrue(Hash::check('strong-password', $student->password));
        $this->assertStringStartsWith('STD', $student->student_id);
        $this->get('/students?search=new.student')->assertOk()->assertSee('new.student@lgl.com');
        $this->get('/students/'.$student->id.'/edit')->assertOk();
        $this->put('/students/'.$student->id, array_merge($this->personData('edited.student@lgl.com'), ['password' => '', 'password_confirmation' => '']))->assertRedirect('/students/'.$student->id);
        $this->assertDatabaseHas('users', ['id' => $student->id, 'email' => 'edited.student@lgl.com', 'role' => 'student']);
        $this->assertTrue(Hash::check('strong-password', $student->fresh()->password));
    }

    public function test_admin_can_manage_teacher_without_payroll_configuration(): void
    {
        $this->get('/teachers/create')->assertOk()->assertDontSee('Salary Amount');
        $this->post('/teachers', $this->personData('new.teacher@lgl.com'))->assertRedirect('/teachers');
        $teacher = User::where('email', 'new.teacher@lgl.com')->sole();
        $this->assertSame('teacher', $teacher->role);
        $this->assertNotEmpty($teacher->employee_id);
        $this->get('/teachers/'.$teacher->id.'/edit')->assertOk();
        $this->put('/teachers/'.$teacher->id, $this->personData('edited.teacher@lgl.com'))->assertRedirect('/teachers/'.$teacher->id);
        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'email' => 'edited.teacher@lgl.com']);
    }

    public function test_wrong_profile_type_cannot_be_modified_through_another_resource(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $this->put('/students/'.$teacher->id, $this->personData('wrong@lgl.com'))->assertNotFound();
        $this->delete('/students/'.$teacher->id)->assertNotFound();
        $this->get('/teachers/'.$student->id)->assertNotFound();
        $this->put('/teachers/'.$student->id, $this->personData('wrong2@lgl.com'))->assertNotFound();
        $this->delete('/teachers/'.$student->id)->assertNotFound();
    }

    public function test_deactivation_preserves_enrollment_and_batch_history(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['name' => 'German', 'level' => 'A1', 'total_fee' => 10000,
            'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
        $batch = Batch::create(['teacher_id' => $teacher->id, 'course_id' => $course->id, 'name' => 'Morning',
            'start_date' => today(), 'status' => 'ongoing', 'max_students' => 10]);
        $enrollment = Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id,
            'enrollment_date' => today(), 'total_fee' => 10000, 'status' => 'active']);
        $this->delete('/students/'.$student->id)->assertRedirect('/students');
        $this->delete('/teachers/'.$teacher->id)->assertRedirect('/teachers');
        $this->delete('/courses/'.$course->id)->assertRedirect('/courses');
        $this->assertDatabaseHas('users', ['id' => $student->id, 'is_active' => false]);
        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'is_active' => false]);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'is_active' => false]);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'total_fee' => 10000]);
        $this->assertDatabaseHas('batches', ['id' => $batch->id]);
    }

    public function test_course_forms_work_and_price_change_preserves_agreed_fee(): void
    {
        $this->get('/courses/create')->assertOk();
        $data = ['name' => 'German A1', 'level' => 'A1', 'description' => 'Beginner German', 'total_fee' => '10000.00',
            'duration_weeks' => 12, 'sessions_per_week' => 2, 'session_duration_minutes' => 90];
        $this->post('/courses', $data)->assertRedirect('/courses');
        $course = Course::where('name', 'German A1')->sole();
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = Batch::create(['teacher_id' => $teacher->id, 'course_id' => $course->id, 'name' => 'Morning',
            'start_date' => today(), 'status' => 'ongoing', 'max_students' => 10]);
        $enrollment = Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id,
            'enrollment_date' => today(), 'total_fee' => 10000, 'discount_amount' => 500]);
        $this->get('/courses/'.$course->id.'/edit')->assertOk();
        $this->put('/courses/'.$course->id, array_merge($data, ['total_fee' => '15000.00']))->assertRedirect('/courses/'.$course->id);
        $this->assertSame('10000.00', $enrollment->fresh()->total_fee);
        $this->assertSame('500.00', $enrollment->fresh()->discount_amount);
    }
}
