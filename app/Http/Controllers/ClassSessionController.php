<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\ClassSession;
use Illuminate\Http\Request;

class ClassSessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher');
    }

    public function index(Request $request)
    {
        $query = ClassSession::with(['batch.course', 'batch.teacher']);

        // Filter by teacher for teacher role
        if (auth()->user()->role === 'teacher') {
            $query->whereHas('batch', function ($q) {
                $q->where('teacher_id', auth()->id());
            });
        }

        // Filter by batch
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('session_date', $request->date);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sessions = $query->orderBy('session_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(15);

        // Get available batches based on role
        if (auth()->user()->role === 'teacher') {
            $batches = Batch::where('teacher_id', auth()->id())
                ->with('course')
                ->orderBy('name')
                ->get();
        } else {
            $batches = Batch::with('course')
                ->orderBy('name')
                ->get();
        }

        return view('admin.class-sessions.index', compact('sessions', 'batches'));
    }

    public function create()
    {
        // Get available batches based on role
        if (auth()->user()->role === 'teacher') {
            $batches = Batch::where('teacher_id', auth()->id())
                ->with('course')
                ->where('status', 'ongoing')
                ->orderBy('name')
                ->get();
        } else {
            $batches = Batch::with('course')
                ->where('status', 'ongoing')
                ->orderBy('name')
                ->get();
        }

        return view('admin.class-sessions.form', ['batches' => $batches, 'classSession' => null]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'session_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'topic' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meeting_link' => 'nullable|url:http,https|max:2000',
            'meeting_id' => 'nullable|string|max:100',
            'meeting_password' => 'nullable|string|max:100',
        ]);

        $batch = Batch::findOrFail($request->batch_id);

        // Check if teacher can create session for this batch
        if (auth()->user()->role === 'teacher' && $batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only create sessions for your assigned batches.');
        }

        $session = ClassSession::create([
            'batch_id' => $request->batch_id,
            'session_date' => $request->session_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'topic' => $request->topic,
            'description' => $request->description,
            'is_online' => $request->is_online,
            'meeting_link' => $request->meeting_link,
            'meeting_id' => $request->meeting_id,
            'meeting_password' => $request->meeting_password,
            'status' => 'scheduled',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('class-sessions.index')
            ->with('success', 'Class session created successfully!');
    }

    public function show(ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only view sessions for your assigned batches.');
        }

        $classSession->load(['batch.course:id,name,level', 'batch.teacher:id,name', 'attendances.student:id,name']);

        return view('admin.class-sessions.show', compact('classSession'));
    }

    public function edit(ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only edit sessions for your assigned batches.');
        }

        // Get available batches based on role
        if (auth()->user()->role === 'teacher') {
            $batches = Batch::where('teacher_id', auth()->id())
                ->with('course')
                ->where('status', 'ongoing')
                ->orderBy('name')
                ->get();
        } else {
            $batches = Batch::with('course')
                ->where('status', 'ongoing')
                ->orderBy('name')
                ->get();
        }

        return view('admin.class-sessions.form', compact('classSession', 'batches'));
    }

    public function update(Request $request, ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only update sessions for your assigned batches.');
        }

        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'session_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'topic' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meeting_link' => 'nullable|url:http,https|max:2000',
            'meeting_id' => 'nullable|string|max:100',
            'meeting_password' => 'nullable|string|max:100',
        ]);

        $batch = Batch::findOrFail($request->batch_id);

        // Check if teacher can update session for this batch
        if (auth()->user()->role === 'teacher' && $batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only update sessions for your assigned batches.');
        }

        if ($batch->id !== $classSession->batch_id && $classSession->attendances()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['batch_id' => 'A session with attendance cannot move to another batch.']);
        }

        $classSession->update([
            'batch_id' => $request->batch_id,
            'session_date' => $request->session_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'topic' => $request->topic,
            'description' => $request->description,
            'is_online' => $request->is_online,
            'meeting_link' => $request->meeting_link,
            'meeting_id' => $request->meeting_id,
            'meeting_password' => $request->meeting_password,
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('class-sessions.index')
            ->with('success', 'Class session updated successfully!');
    }

    public function destroy(ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only delete sessions for your assigned batches.');
        }

        $classSession->update(['status' => 'cancelled']);

        return redirect()->route('class-sessions.index')
            ->with('success', 'Class session cancelled. Attendance history preserved.');
    }

    public function start(ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only start sessions for your assigned batches.');
        }

        if ($classSession->status !== 'scheduled') {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Only scheduled sessions can start.']);
        }
        $classSession->update([
            'status' => 'ongoing',
            'actual_start_time' => now(),
        ]);

        if ($classSession->is_online && $classSession->meeting_link) {
            return redirect()->away($classSession->meeting_link);
        }

        return redirect()->route('class-sessions.show', $classSession)
            ->with('success', 'Class session started!');
    }

    public function end(ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only end sessions for your assigned batches.');
        }

        if (! in_array($classSession->status, ['ongoing', 'completed'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Only ongoing sessions can end.']);
        }
        $classSession->update([
            'status' => 'completed',
            'actual_end_time' => now(),
        ]);

        return redirect()->route('class-sessions.show', $classSession)
            ->with('success', 'Class session ended!');
    }

    public function markAttendance(ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only mark attendance for your assigned batches.');
        }

        $eligibleEnrollments = $classSession->eligibleEnrollments()->with('student:id,name')->get();

        return view('admin.class-sessions.attendance', compact('classSession', 'eligibleEnrollments'));
    }

    public function storeAttendance(Request $request, ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only mark attendance for your assigned batches.');
        }

        if (is_array($request->input('attendance'))) {
            $request->merge(['attendance' => array_filter($request->input('attendance'), fn ($status) => $status !== null && $status !== '')]);
        }
        $request->validate([
            'attendance' => 'required|array',
            'attendance.*' => 'required|in:present,absent,late,excused',
            'notes' => 'nullable|array',
            'reason' => 'nullable|string|min:5|max:1000',
            'notes.*' => 'nullable|string|max:255',
        ]);

        $rows = [];
        foreach ($request->attendance as $studentId => $status) {
            $rows[] = ['student_id' => $studentId, 'status' => $status, 'notes' => $request->input('notes.'.$studentId)];
        }
        app(\App\Services\AttendanceService::class)->markBulkAttendance($classSession->id, $rows, $request->input('reason'));

        return redirect()->route('class-sessions.show', $classSession)
            ->with('success', 'Attendance marked successfully!');
    }
}
