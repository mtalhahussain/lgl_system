<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BiometricPunch;
use App\Models\ClassSession;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\ListQuery;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendanceService)
    {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher')->except(['myAttendance', 'studentAttendance', 'joinOnline']);
    }

    private function batches()
    {
        return Batch::query()->when(auth()->user()->isTeacher(), fn ($query) => $query->where('teacher_id', auth()->id()));
    }

    public function index(Request $request)
    {
        $visible = ClassSession::whereIn('batch_id', $this->batches()->select('id'));
        $batches = $this->batches()->orderBy('name')->pluck('name', 'id');
        $statuses = ['scheduled' => 'Scheduled', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
        $list = ListQuery::for((clone $visible)->with(['batch:id,name,course_id', 'batch.course:id,name'])->withCount('attendances'), $request)
            ->search(['topic', 'batch.name'])
            ->filter('batch_id', fn ($query, $id) => $query->where('batch_id', $id), $batches->keys()->all())
            ->filter('status', fn ($query, $status) => $query->where('status', $status), array_keys($statuses))
            ->filter('marking', fn ($query, $marking) => $marking === 'unmarked'
                ? $query->where('status', '!=', 'cancelled')->whereDate('session_date', '<=', today())->doesntHave('attendances')
                : $query->has('attendances'), ['marked', 'unmarked'])
            ->dateRange('session_date')
            ->sorts(['date' => 'session_date'], '-date');
        $sessions = $list->paginate();
        $attendance = Attendance::whereHas('classSession', fn ($query) => $query->where('status', '!=', 'cancelled')->whereIn('batch_id', $this->batches()->select('id')));
        $summary = $this->attendanceService->statistics($attendance);
        $stats = ['total_sessions' => (clone $visible)->count(), 'completed_sessions' => (clone $visible)->where('status', 'completed')->count(),
            'upcoming_sessions' => (clone $visible)->where('status', 'scheduled')->whereDate('session_date', '>=', today())->count(),
            'unmarked_sessions' => (clone $visible)->where('status', '!=', 'cancelled')->whereDate('session_date', '<=', today())->doesntHave('attendances')->count(),
            'average_attendance' => $summary['attendance_rate']];
        $filters = [['name' => 'batch_id', 'label' => 'Batch', 'options' => $batches], ['name' => 'status', 'label' => 'Status', 'options' => $statuses],
            ['name' => 'marking', 'label' => 'Marking', 'options' => ['unmarked' => 'Needs marking', 'marked' => 'Marked']],
            ['name' => 'from', 'label' => 'From', 'type' => 'date'], ['name' => 'to', 'label' => 'To', 'type' => 'date']];

        return view('admin.attendance.index', compact('sessions', 'stats', 'filters'));
    }

    public function create(Request $request)
    {
        $batches = $this->batches()->where('status', 'ongoing')->with('course:id,name,level')->get();
        $selectedBatch = null;
        $students = collect();
        $date = $request->input('date', today()->toDateString());
        if ($request->filled('batch_id')) {
            $selectedBatch = $this->batches()->with(['course:id,name,level', 'teacher:id,name'])->findOrFail($request->input('batch_id'));
            $students = User::students()->select('id', 'name')->whereHas('enrollments', fn ($query) => $query->where('batch_id', $selectedBatch->id)->where('status', 'active'))->get();
        }

        return view('admin.class-sessions.form', ['batches' => $batches, 'classSession' => null,
            'selectedBatch' => $selectedBatch, 'formAction' => route('attendance.store')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['batch_id' => 'required|exists:batches,id', 'session_date' => 'required|date|before_or_equal:today',
            'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i|after:start_time',
            'topic' => 'required|string|max:255', 'attendance' => 'sometimes|array',
            'meeting_link' => 'nullable|url:http,https|max:2000',
            'attendance.*.student_id' => 'required|integer|exists:users,id', 'attendance.*.status' => 'required|in:present,absent,late,excused',
            'biometric_enabled' => 'prohibited', 'auto_mark_absent' => 'prohibited']);
        $batch = Batch::findOrFail($data['batch_id']);
        $this->authorize('view', $batch);
        DB::transaction(function () use ($data) {
            $session = ClassSession::create(collect($data)->except('attendance')->all() + ['status' => 'scheduled']);
            if (! empty($data['attendance'])) {
                $this->attendanceService->markBulkAttendance($session->id, $data['attendance']);
            }
        });

        return redirect()->route('attendance.index')->with('success', 'Session and manual attendance saved.');
    }

    public function mark(Request $request)
    {
        $data = $request->validate(['session_id' => 'required|exists:class_sessions,id', 'attendance' => 'required|array',
            'attendance.*.student_id' => 'required|integer|exists:users,id', 'attendance.*.status' => 'required|in:present,absent,late,excused',
            'attendance.*.notes' => 'nullable|string|max:500', 'reason' => 'nullable|string|min:5|max:1000']);
        $this->attendanceService->markBulkAttendance($data['session_id'], $data['attendance'], $data['reason'] ?? null);

        return back()->with('success', 'Attendance saved.');
    }

    public function show(ClassSession $session)
    {
        $this->authorize('view', $session->batch);
        $session->load(['batch.course:id,name,level', 'attendances.student:id,name']);

        $session->load(['makeupFor', 'makeupClasses', 'teachingRecorder:id,name']);
        $teacherPunches = BiometricPunch::where('user_id', $session->batch->teacher_id)
            ->where('result', 'teacher')->whereDate('punched_at', $session->session_date->toDateString())
            ->orderBy('punched_at')->get(['punched_at']);

        return view('admin.class-sessions.show', ['classSession' => $session, 'teacherPunches' => $teacherPunches]);
    }

    public function reports(Request $request)
    {
        return $this->attendanceReport($request);
    }

    public function studentAttendance(Request $request, User $student)
    {
        abort_unless($student->isStudent(), 404);
        $this->authorize('view', $student);

        return $this->attendanceReport($request, $student);
    }

    public function myAttendance(Request $request)
    {
        return $this->attendanceReport($request, $request->user());
    }

    public function joinOnline(Request $request, ClassSession $session)
    {
        try {
            $this->attendanceService->selfMarkOnline($session, $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        } catch (QueryException $e) {
            // A concurrent self-mark won the unique (class_session_id, student_id) race;
            // the student is already recorded, so fall through to the meeting link.
        }

        $link = $session->meeting_link ?: $session->batch->meeting_link;
        if (! $link) {
            return back()->with('error', 'No meeting link is set for this class.');
        }

        return redirect()->away($link);
    }

    private function attendanceReport(Request $request, ?User $student = null)
    {
        $data = $request->validate(['start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date', 'batch_id' => 'nullable|integer']);
        $statuses = ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused'];
        $query = Attendance::with(['student:id,name', 'classSession.batch:id,name'])
            ->whereHas('classSession', fn ($query) => $query->where('status', '!=', 'cancelled')->whereIn('batch_id', $this->batches()->select('id'))
                ->when(! empty($data['start_date']), fn ($query) => $query->whereDate('session_date', '>=', $data['start_date']))
                ->when(! empty($data['end_date']), fn ($query) => $query->whereDate('session_date', '<=', $data['end_date']))
                ->when(! empty($data['batch_id']), fn ($query) => $query->where('batch_id', $data['batch_id'])))
            ->when($student, fn ($query) => $query->where('student_id', $student->id));
        $stats = $this->attendanceService->statistics($query);
        $list = ListQuery::for($query, $request)
            ->search($student ? [] : ['student.name'])
            ->filter('status', fn ($query, $status) => $query->where('status', $status), array_keys($statuses))
            ->sorts(['date' => fn ($query, $direction) => $query->orderBy(ClassSession::select('session_date')->whereColumn('class_sessions.id', 'attendances.class_session_id'), $direction),
                'student' => fn ($query, $direction) => $query->orderBy(User::select('name')->whereColumn('users.id', 'attendances.student_id'), $direction)], '-date');
        if ($list->wantsCsv()) {
            return $list->csv('attendance', ['Student' => fn ($row) => $row->student?->name, 'Batch' => fn ($row) => $row->classSession?->batch?->name,
                'Date' => fn ($row) => $row->classSession?->session_date?->toDateString(), 'Status' => fn ($row) => $row->status]);
        }
        $records = $list->paginate(25);
        // Only Admin/Teacher see the batch filter; a Student must not receive other batch names.
        $filters = [['name' => 'start_date', 'label' => 'From', 'type' => 'date'], ['name' => 'end_date', 'label' => 'To', 'type' => 'date']];
        if (! $request->user()->isStudent()) {
            $filters[] = ['name' => 'batch_id', 'label' => 'Batch', 'options' => $this->batches()->orderBy('name')->pluck('name', 'id')];
        }
        $filters[] = ['name' => 'status', 'label' => 'Status', 'options' => $statuses];

        return view('academic.attendance', compact('records', 'stats', 'filters', 'student'));
    }
}
