<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;

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
        if (auth()->user()->isTeacher()) {
            $batches = Batch::where('teacher_id', auth()->id())
                ->select('id', 'name', 'course_id', 'start_date', 'status', 'max_students')
                ->with('course:id,name,level')->withCount('activeEnrollments')
                ->orderByDesc('start_date')->paginate(15);

            return view('academic.batches', compact('batches'));
        }
        $query = Batch::with(['course', 'teacher', 'enrollments']);

        // Filter by teacher for teacher role
        if (auth()->user()->role === 'teacher') {
            $query->where('teacher_id', auth()->id());
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $batches = $query->orderBy('start_date', 'desc')->paginate(15);
        $courses = Course::all();
        $teachers = User::where('role', 'teacher')->get();

        // Adjust stats for teacher view
        if (auth()->user()->role === 'teacher') {
            $stats = [
                'total' => Batch::where('teacher_id', auth()->id())->count(),
                'active' => Batch::where('teacher_id', auth()->id())->where('status', 'ongoing')->count(),
                'completed' => Batch::where('teacher_id', auth()->id())->where('status', 'completed')->count(),
                'upcoming' => Batch::where('teacher_id', auth()->id())->where('status', 'upcoming')->count(),
            ];
        } else {
            $stats = [
                'total' => Batch::count(),
                'active' => Batch::where('status', 'ongoing')->count(),
                'completed' => Batch::where('status', 'completed')->count(),
                'upcoming' => Batch::where('status', 'upcoming')->count(),
            ];
        }

        return view('admin.batches.index', compact('batches', 'courses', 'teachers', 'stats'));
    }

    public function create()
    {
        $courses = Course::all();
        $teachers = User::where('role', 'teacher')->get();

        return view('admin.batches.form', ['batch' => null, 'courses' => $courses, 'teachers' => $teachers]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedBatch($request);
        Batch::create($data);

        return redirect()->route('batches.index')->with('success', 'Batch created successfully.');
    }

    public function show(Batch $batch)
    {
        $this->authorize('view', $batch);
        $batch = Batch::select('id', 'name', 'course_id', 'start_date', 'end_date', 'status', 'meeting_link')->findOrFail($batch->id);
        $batch->load('course:id,name,level');
        $students = User::students()->select('id', 'name')
            ->whereHas('enrollments', fn ($query) => $query->where('batch_id', $batch->id)->where('status', 'active'))
            ->orderBy('name')->get();

        return view('academic.batch', compact('batch', 'students'));
    }

    public function edit(Batch $batch)
    {
        $courses = Course::all();
        $teachers = User::where('role', 'teacher')->get();

        return view('admin.batches.form', compact('batch', 'courses', 'teachers'));
    }

    public function update(Request $request, Batch $batch)
    {
        $data = $this->validatedBatch($request);
        \Illuminate\Support\Facades\DB::transaction(function () use ($batch, $data) {
            $batch = Batch::lockForUpdate()->findOrFail($batch->id);
            if ((int) $data['course_id'] !== $batch->course_id && $batch->enrollments()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['course_id' => 'An enrolled batch must keep its original course.']);
            }
            if ($data['max_students'] < $batch->activeEnrollments()->count()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['max_students' => 'Capacity cannot be lower than active enrollment count.']);
            }
            $before = $batch->only(['teacher_id', 'max_students', 'status']);
            $batch->update($data);
            \App\Models\AuditLog::create(['actor_id' => auth()->id(), 'action' => 'batch.updated',
                'subject_type' => 'batch', 'subject_id' => $batch->id,
                'changes' => ['before' => $before, 'after' => $batch->only(['teacher_id', 'max_students', 'status'])]]);
        }, 3);

        return redirect()->route('batches.show', $batch)->with('success', 'Batch updated successfully.');
    }

    private function validatedBatch(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'course_id' => ['required', \Illuminate\Validation\Rule::exists('courses', 'id')->where('is_active', true)],
            'teacher_id' => ['required', \Illuminate\Validation\Rule::exists('users', 'id')->where('role', 'teacher')->where('is_active', true)],
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'max_students' => 'required|integer|min:1|max:500',
            'status' => 'required|in:upcoming,ongoing,completed,cancelled',
            'meeting_platform' => 'required|in:in_person,zoom,google_meet',
            'meeting_link' => 'nullable|url:http,https|max:2000',
            'notes' => 'nullable|string|max:2000',
        ]);
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
