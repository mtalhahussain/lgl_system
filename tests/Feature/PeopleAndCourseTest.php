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

    public function test_contact_inputs_reject_text_and_preserve_phone_digits(): void
    {
        foreach (['teachers', 'students'] as $resource) {
            $data = $this->personData('contact.'.$resource.'@lgl.com');
            $this->postJson('/'.$resource, array_replace($data, ['phone' => 'abc', 'emergency_contact' => 'A contact name']))
                ->assertUnprocessable()->assertJsonValidationErrors(['phone', 'emergency_contact']);
            $this->post('/'.$resource, $data + ['emergency_contact' => '+923001234567'])->assertRedirect();
            $person = User::where('email', $data['email'])->sole();
            $this->assertSame('03001234567', $person->phone);
            $this->assertSame('+923001234567', $person->emergency_contact);
            $this->putJson('/'.$resource.'/'.$person->id, array_replace($data, ['emergency_contact' => '0300abc']))
                ->assertUnprocessable()->assertJsonValidationErrors('emergency_contact');
        }
        $this->putJson('/profile', ['name' => 'Admin', 'phone' => '1e10', 'emergency_contact' => 'text'])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone', 'emergency_contact']);
    }

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

    public function test_teacher_and_student_passwords_can_be_set_later_and_blank_edits_preserve_hash(): void
    {
        foreach (['teachers' => 'teacher', 'students' => 'student'] as $resource => $role) {
            $data = $this->personData('optional.'.$role.'@lgl.com');
            unset($data['password'], $data['password_confirmation']);
            $this->post('/'.$resource, $data + ['password' => '', 'password_confirmation' => ''])->assertRedirect('/'.$resource);
            $person = User::where('email', $data['email'])->sole();
            $this->assertNotEmpty($person->password);
            $this->assertNotSame('', $person->password);
            $this->assertFalse(Hash::check('', $person->password));
            $this->assertFalse(Hash::check('password', $person->password));
            $hash = $person->password;
            $this->put('/'.$resource.'/'.$person->id, $data + ['password' => '', 'password_confirmation' => ''])->assertRedirect();
            $this->assertSame($hash, $person->fresh()->password);
            $this->put('/'.$resource.'/'.$person->id, $data + ['password' => 'chosen-password', 'password_confirmation' => 'chosen-password'])->assertRedirect();
            $this->assertTrue(Hash::check('chosen-password', $person->fresh()->password));
            $this->put('/'.$resource.'/'.$person->id, $data)->assertRedirect();
            $this->assertTrue(Hash::check('chosen-password', $person->fresh()->password));
            $this->post('/'.$resource, array_replace($data, ['email' => 'omitted.'.$role.'@lgl.com']))->assertRedirect();
            $this->assertNotEmpty(User::where('email', 'omitted.'.$role.'@lgl.com')->sole()->password);
        }
    }

    public function test_optional_provisioning_passwords_still_require_length_and_matching_confirmation(): void
    {
        foreach (['teachers', 'students'] as $resource) {
            $data = $this->personData('invalid.'.$resource.'@lgl.com');
            foreach ([['short', 'short'], ['long-password', 'mismatch'], ['long-password', ''], ['', 'confirmation-only']] as [$password, $confirmation]) {
                $this->postJson('/'.$resource, array_replace($data, ['password' => $password, 'password_confirmation' => $confirmation]))
                    ->assertUnprocessable()->assertJsonValidationErrors('password');
            }
        }
    }

    public function test_teacher_per_student_rate_is_required_validated_and_saved(): void
    {
        $data = $this->personData('rate.teacher@lgl.com') + ['salary_type' => 'per_student'];
        foreach ([null, '0', '-1', '1.001', '100000000'] as $invalid) {
            $this->postJson('/teachers', $data + ['per_student_amount' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('per_student_amount');
        }
        $this->post('/teachers', $data + ['per_student_amount' => '1000.25'])->assertRedirect('/teachers');
        $teacher = User::where('email', $data['email'])->sole();
        $this->assertSame('1000.25', $teacher->per_student_amount);
        $this->get('/teachers/'.$teacher->id.'/edit')->assertOk()->assertSee('Amount per student per month');
        $this->putJson('/teachers/'.$teacher->id, $data)->assertUnprocessable()->assertJsonValidationErrors('per_student_amount');
        $this->put('/teachers/'.$teacher->id, $data + ['per_student_amount' => '1200.50'])->assertRedirect();
        $this->assertSame('1200.50', $teacher->fresh()->per_student_amount);
        $this->put('/teachers/'.$teacher->id, array_replace($data, ['salary_type' => 'monthly', 'monthly_salary' => '30000']))->assertRedirect();
        $this->assertSame('1200.50', $teacher->fresh()->per_student_amount);
    }

    public function test_teaching_dropdowns_validate_choices_and_preserve_existing_values(): void
    {
        $this->get('/teachers/create')->assertOk()
            ->assertSee('name="qualification"', false)->assertSee('Goethe-Zertifikat C1')
            ->assertSee('German for Ausbildung')->assertSee('0 years (New teacher)')
            ->assertDontSee('<input class="form-control" id="qualification"', false);
        $data = $this->personData('choices.teacher@lgl.com') + [
            'qualification' => 'Goethe-Zertifikat C1', 'specialization' => 'A1', 'experience_years' => 0,
        ];
        $this->post('/teachers', $data)->assertRedirect('/teachers');
        $teacher = User::where('email', $data['email'])->sole();
        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'qualification' => 'Goethe-Zertifikat C1', 'experience_years' => 0]);
        $invalid = array_replace($data, ['qualification' => 'Invalid qualification', 'specialization' => 'Invalid specialty', 'experience_years' => 51]);
        $this->postJson('/teachers', array_replace($invalid, ['email' => 'invalid.teacher@lgl.com']))
            ->assertUnprocessable()->assertJsonValidationErrors(['qualification', 'specialization', 'experience_years']);
        $this->putJson('/teachers/'.$teacher->id, $invalid)->assertUnprocessable()
            ->assertJsonValidationErrors(['qualification', 'specialization', 'experience_years']);
        $teacher->update(['qualification' => 'Existing custom diploma', 'specialization' => 'Existing specialty', 'experience_years' => 55]);
        $this->get('/teachers/'.$teacher->id.'/edit')->assertOk()
            ->assertSee('Existing custom diploma')->assertSee('Existing specialty')->assertSee('55 years');
        $this->put('/teachers/'.$teacher->id, array_replace($data, [
            'qualification' => 'Existing custom diploma', 'specialization' => 'Existing specialty', 'experience_years' => 55,
        ]))->assertRedirect('/teachers/'.$teacher->id);
        $this->put('/teachers/'.$teacher->id, array_replace($data, [
            'qualification' => 'Master in German Studies', 'specialization' => 'TestDaF Preparation', 'experience_years' => 5,
        ]))->assertRedirect('/teachers/'.$teacher->id);
        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'qualification' => 'Master in German Studies',
            'specialization' => 'TestDaF Preparation', 'experience_years' => 5]);
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
        // A completed batch has no open commitment, so the teacher can be deactivated directly;
        // upcoming/ongoing batches must be reassigned first (see TeacherReassignTest).
        $batch = Batch::create(['teacher_id' => $teacher->id, 'course_id' => $course->id, 'name' => 'Morning',
            'start_date' => today()->subMonth(), 'end_date' => today()->subDay(), 'status' => 'completed', 'max_students' => 10]);
        $enrollment = Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id,
            'enrollment_date' => today()->subMonth(), 'total_fee' => 10000, 'status' => 'active']);
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
