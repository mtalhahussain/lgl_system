<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Batch;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
            $query->whereHas('batch', function($q) {
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
                           ->where('status', 'active')
                           ->orderBy('name')
                           ->get();
        } else {
            $batches = Batch::with('course')
                           ->where('status', 'active')
                           ->orderBy('name')
                           ->get();
        }

        return view('admin.class-sessions.create', compact('batches'));
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
            'is_online' => 'required|boolean',
            'meeting_link' => 'required_if:is_online,1|nullable|url',
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

        $classSession->load(['batch.course', 'batch.teacher', 'attendances.student']);

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
                           ->where('status', 'active')
                           ->orderBy('name')
                           ->get();
        } else {
            $batches = Batch::with('course')
                           ->where('status', 'active')
                           ->orderBy('name')
                           ->get();
        }

        return view('admin.class-sessions.edit', compact('classSession', 'batches'));
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
            'is_online' => 'required|boolean',
            'meeting_link' => 'required_if:is_online,1|nullable|url',
            'meeting_id' => 'nullable|string|max:100',
            'meeting_password' => 'nullable|string|max:100',
        ]);

        $batch = Batch::findOrFail($request->batch_id);
        
        // Check if teacher can update session for this batch
        if (auth()->user()->role === 'teacher' && $batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only update sessions for your assigned batches.');
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

        // Don't allow deleting if attendance is already marked
        if ($classSession->attendances()->count() > 0) {
            return redirect()->route('class-sessions.index')
                            ->with('error', 'Cannot delete session with existing attendance records.');
        }

        $classSession->delete();

        return redirect()->route('class-sessions.index')
                        ->with('success', 'Class session deleted successfully!');
    }

    public function start(ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only start sessions for your assigned batches.');
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

        $classSession->load(['batch.activeEnrollments.student']);

        return view('admin.class-sessions.attendance', compact('classSession'));
    }

    public function storeAttendance(Request $request, ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only mark attendance for your assigned batches.');
        }

        $request->validate([
            'attendance' => 'required|array',
            'attendance.*' => 'required|in:present,absent,late,excused',
            'notes.*' => 'nullable|string|max:255',
        ]);

        foreach ($request->attendance as $studentId => $status) {
            Attendance::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'class_session_id' => $classSession->id,
                ],
                [
                    'status' => $status,
                    'notes' => $request->notes[$studentId] ?? null,
                    'marked_by' => auth()->id(),
                ]
            );
        }

        return redirect()->route('class-sessions.show', $classSession)
                        ->with('success', 'Attendance marked successfully!');
    }
}