<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\PaymentService;
use App\Support\ListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    private Batch $batch;

    private Batch $otherBatch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $otherTeacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->student = User::factory()->create(['role' => 'student', 'name' => 'Active Learner', 'is_active' => true]);
        User::factory()->create(['role' => 'student', 'name' => 'Former Learner', 'is_active' => false]);
        $course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => '300.00', 'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
        $this->batch = Batch::create(['course_id' => $course->id, 'teacher_id' => $this->teacher->id, 'name' => 'Assigned Batch',
            'start_date' => today()->subMonths(2), 'status' => 'ongoing', 'max_students' => 10]);
        $this->otherBatch = Batch::create(['course_id' => $course->id, 'teacher_id' => $otherTeacher->id, 'name' => 'Unrelated Batch',
            'start_date' => today(), 'status' => 'upcoming', 'max_students' => 10]);
        $enrollment = app(EnrollmentService::class)->enrollStudent($this->student->id, $this->batch->id, 0, 3);
        $enrollment->feeInstallments()->orderBy('due_date')->first()->forceFill(['due_date' => today()->subWeek()])->saveQuietly();
        app(PaymentService::class)->pay($enrollment->id, '50.00', 'cash', (string) Str::uuid(), $this->admin);
        $session = ClassSession::create(['batch_id' => $this->batch->id, 'session_date' => today(), 'start_time' => '09:00',
            'end_time' => '10:00', 'topic' => 'Greetings', 'status' => 'scheduled']);
        Attendance::create(['class_session_id' => $session->id, 'student_id' => $this->student->id, 'status' => 'present']);
    }

    public function test_every_admin_list_page_renders_with_filters_sorting_and_page_size(): void
    {
        $this->actingAs($this->admin);
        foreach (['/students?status=active&sort=name&dir=asc&per_page=25', '/teachers?status=active&sort=joined', '/accounts?role=student&sort=role',
            '/courses?level=A1&sort=fee&dir=desc', '/batches?status=ongoing&platform=in_person&sort=capacity', '/class-sessions?from='.today()->toDateString().'&sort=topic',
            '/enrollments?status=active&sort=student', '/fees?due=overdue&sort=balance', '/fees/reports?method=cash', '/reports',
            '/reports/enrollments?status=active&sort=student', '/reports/dues?overdue=1&sort=amount', '/attendance?marking=marked',
            '/attendance/reports?status=present&sort=student', '/installments/create'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_page_size_is_above_filters_and_retains_filters_without_current_page(): void
    {
        $this->actingAs($this->admin);
        $response = $this->get('/students?search=Learner&status=active&sort=name&dir=asc&per_page=25&page=2')->assertOk();
        $html = $response->getContent();
        preg_match('/<form[^>]*data-page-size[^>]*>([\s\S]*?)<\/form>/', $html, $footer);
        $this->assertNotEmpty($footer);
        $this->assertStringContainsString('id="list_search" form="list-filters"', $html);
        $this->assertStringContainsString('id="list-filters" class="list-filter"', $html);
        $this->assertLessThan(strpos($html, 'data-list-filter'), strpos($html, 'data-page-size'));
        preg_match('/<div class="list-footer">([\s\S]*?)<\/div>/', $html, $tableFooter);
        $this->assertStringNotContainsString('data-page-size', $tableFooter[1]);
        $this->assertStringContainsString('Rows per page', $footer[1]);
        $this->assertStringContainsString('name="search" value="Learner"', $footer[1]);
        $this->assertStringContainsString('name="status" value="active"', $footer[1]);
        $this->assertStringContainsString('name="sort" value="name"', $footer[1]);
        $this->assertStringContainsString('name="dir" value="asc"', $footer[1]);
        $this->assertStringNotContainsString('name="page"', $footer[1]);
        $this->assertStringContainsString('value="25" selected', $footer[1]);
        preg_match('/<form[^>]*data-list-filter[^>]*>([\s\S]*?)<\/form>/', $html, $toolbar);
        $this->assertStringNotContainsString('Rows per page', $toolbar[1]);
        $this->assertStringContainsString('type="hidden" name="per_page" value="25"', $toolbar[1]);
        $this->get('/reports/enrollments')->assertOk()->assertSee('value="25" selected', false);
        $this->get('/fees/reports')->assertOk()->assertDontSee('data-page-size', false);
        $this->get('/fees')->assertOk()->assertSee('data-page-size', false);
    }

    public function test_filters_change_rows_and_chips_offer_removal(): void
    {
        $this->actingAs($this->admin);
        $this->get('/students?status=inactive')->assertOk()->assertSee('Former Learner')->assertDontSee('Active Learner')
            ->assertSee('Status: Inactive')->assertSee('Clear all');
        $this->get('/fees?due=overdue')->assertOk()->assertSee('Active Learner')->assertViewHas('enrollments', fn ($rows) => $rows->total() === 1);
        $this->get('/fees?due=paid')->assertOk()->assertViewHas('enrollments', fn ($rows) => $rows->total() === 0);
        $this->get('/reports/dues?overdue=1')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
    }

    public function test_unknown_sort_filter_and_page_size_values_are_ignored(): void
    {
        $this->actingAs($this->admin);
        $this->get('/students?sort=password&dir=sideways&per_page=100000&status=root')->assertOk()
            ->assertViewHas('people', fn ($rows) => $rows->perPage() === 15 && $rows->total() === 2);
        $this->get('/students?search='.urlencode('%'))->assertOk()->assertViewHas('people', fn ($rows) => $rows->total() === 0);
    }

    public function test_admin_csv_streams_filtered_rows_and_neutralises_formulas(): void
    {
        $this->actingAs($this->admin);
        User::factory()->create(['role' => 'student', 'name' => '=HYPERLINK("http://x")', 'is_active' => true]);
        $csv = $this->get('/students?export=csv&status=active')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString('Active Learner', $csv);
        $this->assertStringNotContainsString('Former Learner', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('password', strtolower($csv));
        $collections = $this->get('/fees/reports?export=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Payment', $collections);
        $this->assertStringContainsString('50.00', $collections);
    }

    public function test_csv_and_other_batch_names_are_never_given_to_teachers_or_students(): void
    {
        $this->actingAs($this->teacher)->get('/attendance/reports?export=csv')->assertForbidden();
        $this->get('/students')->assertOk()->assertDontSee('Unrelated Batch');
        $this->get('/class-sessions')->assertOk()->assertDontSee('Unrelated Batch')->assertDontSee('CSV');
        $this->get('/attendance')->assertOk()->assertDontSee('Unrelated Batch');
        $this->actingAs($this->student)->get('/student/attendance')->assertOk()->assertDontSee('Unrelated Batch')->assertDontSee('filter_batch_id');
        $this->get('/student/attendance?export=csv')->assertForbidden();
        $this->get('/fees/student/'.$this->student->id)->assertOk()->assertDontSee('Collected this month')->assertDontSee('Unrelated Batch');
    }

    public function test_list_query_cell_and_per_page_helpers(): void
    {
        $this->assertSame("'=1+1", ListQuery::cell('=1+1'));
        $this->assertSame('-10.00', ListQuery::cell('-10.00'));
        $this->assertSame('plain', ListQuery::cell('plain'));
        $list = ListQuery::for(User::query(), Request::create('/', 'GET', ['per_page' => '50']));
        $this->assertSame(50, $list->perPage());
        $this->assertSame(15, ListQuery::for(User::query(), Request::create('/', 'GET', ['per_page' => '7']))->perPage());
    }
}
