<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BatchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher')->only(['index', 'show']);
        $this->middleware('role:admin')->except(['apiIndex', 'apiShow', 'index', 'show']);
    }

    public function index(Request $request)
    {
        $statuses = ['upcoming' => 'Upcoming', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
        if (auth()->user()->isTeacher()) {
            $list = ListQuery::for(Batch::where('teacher_id', auth()->id())
                ->select('id', 'name', 'course_id', 'start_date', 'status', 'max_students')
                ->with('course:id,name,level')->withCount('activeEnrollments'), $request)
                ->search(['name'])
                ->filter('status', fn ($query, $status) => $query->where('status', $status), array_keys($statuses))
                ->sorts(['name' => 'name', 'start' => 'start_date'], '-start');
            $batches = $list->paginate();
            $filters = [['name' => 'status', 'label' => 'Status', 'options' => $statuses]];

            return view('academic.batches', compact('batches', 'filters'));
        }
        $courses = Course::orderBy('name')->pluck('name', 'id');
        $teachers = User::where('role', 'teacher')->orderBy('name')->pluck('name', 'id');
        $platforms = ['zoom' => 'Zoom', 'google_meet' => 'Google Meet', 'in_person' => 'In person'];
        $list = ListQuery::for(Batch::with(['course:id,name,level', 'teacher:id,name'])->withCount('activeEnrollments'), $request)
            ->search(['name', 'course.name'])
            ->filter('course_id', fn ($query, $id) => $query->where('course_id', $id), $courses->keys()->all())
            ->filter('teacher_id', fn ($query, $id) => $query->where('teacher_id', $id), $teachers->keys()->all())
            ->filter('status', fn ($query, $status) => $query->where('status', $status), array_keys($statuses))
            ->filter('platform', fn ($query, $platform) => $query->where('meeting_platform', $platform), array_keys($platforms))
            ->sorts(['name' => 'name', 'start' => 'start_date', 'capacity' => 'max_students'], '-start');
        if ($list->wantsCsv()) {
            return $list->csv('batches', ['Batch' => fn ($batch) => $batch->name, 'Course' => fn ($batch) => $batch->course?->name,
                'Teacher' => fn ($batch) => $batch->teacher?->name, 'Start' => fn ($batch) => $batch->start_date?->toDateString(),
                'End' => fn ($batch) => $batch->end_date?->toDateString(), 'Active students' => fn ($batch) => $batch->active_enrollments_count,
                'Capacity' => fn ($batch) => $batch->max_students, 'Status' => fn ($batch) => $batch->status,
                'Platform' => fn ($batch) => $platforms[$batch->meeting_platform] ?? $batch->meeting_platform]);
        }
        $counts = $list->counts(['ongoing' => "batches.status = 'ongoing'", 'upcoming' => "batches.status = 'upcoming'", 'completed' => "batches.status = 'completed'"]);
        $open = $list->totalsQuery()->whereIn('status', ['upcoming', 'ongoing'])->withCount('activeEnrollments')->get(['id', 'max_students']);
        $kpis = [['label' => 'Ongoing', 'value' => $counts['ongoing'], 'icon' => 'fa-play'],
            ['label' => 'Upcoming', 'value' => $counts['upcoming'], 'icon' => 'fa-clock'],
            ['label' => 'Completed', 'value' => $counts['completed'], 'icon' => 'fa-check'],
            ['label' => 'Seats left', 'value' => $open->sum(fn ($batch) => max(0, $batch->max_students - $batch->active_enrollments_count)), 'icon' => 'fa-chair', 'note' => 'In upcoming and ongoing batches']];
        $filters = [['name' => 'course_id', 'label' => 'Course', 'options' => $courses], ['name' => 'teacher_id', 'label' => 'Teacher', 'options' => $teachers],
            ['name' => 'status', 'label' => 'Status', 'options' => $statuses], ['name' => 'platform', 'label' => 'Platform', 'options' => $platforms]];

        return view('admin.batches.index', ['batches' => $list->paginate(), 'kpis' => $kpis, 'filters' => $filters, 'platforms' => $platforms]);
    }

    public function create()
    {
        $courses = Course::all();
        $teachers = User::where('role', 'teacher')->get();

        return view('admin.batches.form', ['batch' => null, 'courses' => $courses, 'teachers' => $teachers]);
    }

    public function store(Request $request)
    {
        [$data, $schedule] = $this->validatedBatch($request);
        $batch = Batch::create($data);
        $this->saveSchedule($batch, $schedule);

        return redirect()->route('batches.index')->with('success', 'Batch created successfully.');
    }

    public function show(Batch $batch)
    {
        $this->authorize('view', $batch);
        $batch = Batch::select('id', 'name', 'course_id', 'teacher_id', 'start_date', 'end_date', 'status', 'max_students', 'meeting_platform', 'meeting_link')->findOrFail($batch->id);
        $batch->load(['course:id,name,level', 'teacher:id,name', 'schedules']);
        $students = Enrollment::where('batch_id', $batch->id)->whereIn('status', ['active', 'completed'])
            ->with('student:id,name')->get(['id', 'student_id', 'enrollment_date', 'status'])
            ->sortBy(fn ($row) => $row->student?->name)->values();
        $sessions = ClassSession::where('batch_id', $batch->id)->where('status', '!=', 'cancelled')
            ->select('id', 'topic', 'session_date', 'start_time', 'end_time', 'status')->orderBy('session_date')->orderBy('start_time')->get();
        $upcoming = $sessions->filter(fn ($session) => $session->status === 'scheduled' && $session->session_date->gte(today()))->take(5);
        $held = $sessions->where('status', 'completed')->count();

        return view('academic.batch', compact('batch', 'students', 'upcoming', 'held'));
    }

    public function edit(Batch $batch)
    {
        $courses = Course::all();
        $teachers = User::where('role', 'teacher')->get();

        return view('admin.batches.form', compact('batch', 'courses', 'teachers'));
    }

    public function update(Request $request, Batch $batch)
    {
        [$data, $schedule] = $this->validatedBatch($request);
        DB::transaction(function () use ($batch, $data, $schedule) {
            $batch = Batch::lockForUpdate()->findOrFail($batch->id);
            if ((int) $data['course_id'] !== $batch->course_id && $batch->enrollments()->exists()) {
                throw ValidationException::withMessages(['course_id' => 'An enrolled batch must keep its original course.']);
            }
            if ($data['max_students'] < $batch->activeEnrollments()->count()) {
                throw ValidationException::withMessages(['max_students' => 'Capacity cannot be lower than active enrollment count.']);
            }
            $before = $batch->only(['teacher_id', 'max_students', 'status']);
            $batch->update($data);
            $this->saveSchedule($batch, $schedule);
            $batch->syncEnrollmentStatuses(auth()->id());
            AuditLog::create(['actor_id' => auth()->id(), 'action' => 'batch.updated',
                'subject_type' => 'batch', 'subject_id' => $batch->id,
                'changes' => ['before' => $before, 'after' => $batch->only(['teacher_id', 'max_students', 'status'])]]);
        }, 3);

        return redirect()->route('batches.show', $batch)->with('success', 'Batch updated successfully.');
    }

    private function validatedBatch(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'course_id' => ['required', Rule::exists('courses', 'id')->where('is_active', true)],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where('role', 'teacher')->where('is_active', true)],
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'max_students' => 'required|integer|min:1|max:500',
            // Only cancellation is manual; any other value means "follow the dates".
            'status' => 'nullable|in:auto,upcoming,ongoing,completed,cancelled',
            'meeting_platform' => 'required|in:in_person,zoom,google_meet',
            'meeting_link' => 'nullable|url:http,https|max:2000',
            'notes' => 'nullable|string|max:2000',
            // Recurring weekly class days/times (optional template).
            'schedule' => 'nullable|array|max:50',
            'schedule.*.weekday' => 'nullable|in:mon,tue,wed,thu,fri,sat,sun',
            'schedule.*.start' => 'nullable|date_format:H:i',
            'schedule.*.end' => 'nullable|date_format:H:i',
        ]);
        $data['status'] = ($data['status'] ?? null) === 'cancelled'
            ? 'cancelled' : Batch::statusForDates($data['start_date'], $data['end_date'] ?? null);

        $schedule = $this->cleanSchedule($request->input('schedule', []));
        unset($data['schedule']);

        return [$data, $schedule];
    }

    /** Drops blank rows, requires each used row to be complete with end after start. */
    private function cleanSchedule(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $weekday = $row['weekday'] ?? null;
            $start = $row['start'] ?? null;
            $end = $row['end'] ?? null;
            if (! $weekday && ! $start && ! $end) {
                continue;
            }
            if (! $weekday || ! $start || ! $end) {
                throw ValidationException::withMessages(['schedule' => 'Each class day needs a day, start time and end time.']);
            }
            if (strtotime($end) <= strtotime($start)) {
                throw ValidationException::withMessages(['schedule' => 'Class end time must be after start time.']);
            }
            $clean[] = ['weekday' => $weekday, 'start_time' => $start, 'end_time' => $end];
        }

        return $clean;
    }

    private function saveSchedule(Batch $batch, array $schedule): void
    {
        $batch->schedules()->delete();
        foreach ($schedule as $row) {
            $batch->schedules()->create($row);
        }
    }

    public function destroy(Batch $batch)
    {
        $batch->update(['status' => 'cancelled']);

        return redirect()->route('batches.index')->with('success', 'Batch cancelled. Enrollment and session history preserved.');
    }

    // API methods for backward compatibility
    public function apiIndex(Request $request)
    {
        $this->authorize('viewAny', Batch::class);

        $query = Batch::with(['course', 'teacher']);

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $batches = $query->paginate(10);

        return response()->json($batches);
    }

    public function apiShow(Batch $batch)
    {
        $this->authorize('view', $batch);
        $batch->load(['course', 'teacher', 'activeEnrollments', 'classSessions']);

        return response()->json($batch);
    }

    public function enrollments(Batch $batch)
    {
        $this->authorize('view', $batch);

        $enrollments = $batch->enrollments()
            ->with(['student', 'feeInstallments'])
            ->paginate(15);

        return response()->json($enrollments);
    }

    public function sessions(Batch $batch)
    {
        $this->authorize('view', $batch);

        $sessions = $batch->classeSessions()
            ->with(['attendances.student'])
            ->orderBy('session_date', 'desc')
            ->paginate(15);

        return response()->json($sessions);
    }

    public function earnings(Batch $batch)
    {
        $this->authorize('viewFinancials', $batch);

        $earnings = $batch->teacherEarnings()
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(12);

        return response()->json($earnings);
    }
}
