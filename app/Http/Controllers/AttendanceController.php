<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendanceService)
    {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher')->except(['myAttendance', 'studentAttendance']);
    }

    private function batches()
    {
        return Batch::query()->when(auth()->user()->isTeacher(), fn ($query) => $query->where('teacher_id', auth()->id()));
    }

    public function index(Request $request)
    {
        $visible = ClassSession::whereIn('batch_id', $this->batches()->select('id'));
        $sessions = (clone $visible)->with(['batch.course:id,name,level', 'batch.teacher:id,name', 'attendances.student:id,name'])
            ->when($request->filled('batch_id'), fn ($query) => $query->where('batch_id', $request->input('batch_id')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('session_date', $request->input('date')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderByDesc('session_date')->paginate(15);
        $batches = $this->batches()->with('course:id,name,level')->get();
        $attendance = Attendance::whereHas('classSession', fn ($query) => $query->where('status', '!=', 'cancelled')->whereIn('batch_id', $this->batches()->select('id')));
        $summary = $this->attendanceService->statistics($attendance);
        $stats = ['total_sessions' => (clone $visible)->count(), 'completed_sessions' => (clone $visible)->where('status', 'completed')->count(),
            'upcoming_sessions' => (clone $visible)->where('status', 'scheduled')->whereDate('session_date', '>=', today())->count(),
            'average_attendance' => $summary['attendance_rate']];

        return view('admin.attendance.index', compact('sessions', 'batches', 'stats'));
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

        return view('admin.class-sessions.show', ['classSession' => $session]);
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

    private function attendanceReport(Request $request, ?User $student = null)
    {
        $data = $request->validate(['start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date', 'batch_id' => 'nullable|integer']);
        $query = Attendance::with(['student:id,name', 'classSession.batch:id,name'])
            ->whereHas('classSession', fn ($query) => $query->where('status', '!=', 'cancelled')->whereIn('batch_id', $this->batches()->select('id'))
                ->when(! empty($data['start_date']), fn ($query) => $query->whereDate('session_date', '>=', $data['start_date']))
                ->when(! empty($data['end_date']), fn ($query) => $query->whereDate('session_date', '<=', $data['end_date']))
                ->when(! empty($data['batch_id']), fn ($query) => $query->where('batch_id', $data['batch_id'])))
            ->when($student, fn ($query) => $query->where('student_id', $student->id));
        $stats = $this->attendanceService->statistics($query);
        $records = $query->orderByDesc('id')->paginate(30)->withQueryString();

        return view('academic.attendance', compact('records', 'stats'));
    }
}
