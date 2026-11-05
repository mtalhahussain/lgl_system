<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Batch;
use App\Support\ListQuery;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher')->only(['index', 'show']);
        $this->middleware('role:admin')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            $courses = Course::select('id', 'name', 'level', 'duration_weeks')
                ->whereHas('batches', fn ($query) => $query->where('teacher_id', auth()->id()))
                ->orderBy('level')->get();

            return view('academic.courses', compact('courses'));
        }
        $levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
        $list = ListQuery::for(Course::withCount(['batches', 'batches as active_batches_count' => fn ($query) => $query->where('status', 'ongoing')]), $request)
            ->search(['name', 'description'])
            ->filter('level', fn ($query, $level) => $query->where('level', $level), $levels)
            ->filter('status', fn ($query, $status) => $query->where('is_active', $status === 'active'), ['active', 'inactive'])
            ->sorts(['name' => 'name', 'level' => 'level', 'fee' => 'total_fee', 'duration' => 'duration_weeks'], 'level');
        if ($list->wantsCsv()) {
            return $list->csv('courses', ['Name' => fn ($course) => $course->name, 'Level' => fn ($course) => $course->level,
                'Weeks' => fn ($course) => $course->duration_weeks, 'Fee' => fn ($course) => $course->total_fee,
                'Batches' => fn ($course) => $course->batches_count, 'Ongoing batches' => fn ($course) => $course->active_batches_count,
                'Status' => fn ($course) => $course->is_active ? 'Active' : 'Inactive']);
        }
        $counts = $list->counts(['active' => 'courses.is_active = 1']);
        $kpis = [['label' => 'Courses', 'value' => $counts['total'], 'icon' => 'fa-book'],
            ['label' => 'Active courses', 'value' => $counts['active'], 'icon' => 'fa-check'],
            ['label' => 'Ongoing batches', 'value' => Batch::where('status', 'ongoing')->whereIn('course_id', $list->totalsQuery()->select('courses.id'))->count(), 'icon' => 'fa-play'],
            ['label' => 'Active students', 'value' => \App\Models\Enrollment::active()->whereHas('batch', fn ($batch) => $batch->whereIn('course_id', $list->totalsQuery()->select('courses.id')))->distinct()->count('student_id'), 'icon' => 'fa-users']];
        $filters = [['name' => 'level', 'label' => 'Level', 'options' => array_combine($levels, $levels)],
            ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]];

        return view('admin.courses.index', ['courses' => $list->paginate(), 'kpis' => $kpis, 'filters' => $filters]);
    }

    public function create()
    {
        return view('admin.courses.form', ['course' => null]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('courses', 'name')->where('level', $request->input('level'))],
            'description' => 'required|string|max:1000',
            'level' => 'required|in:A1,A2,B1,B2,C1,C2',
            'duration_weeks' => 'required|integer|min:1|max:52',
            'total_fee' => 'required|decimal:0,2|min:0|max:99999999.99',
            'teacher_per_student_amount' => 'nullable|numeric|min:0',
            'sessions_per_week' => 'required|integer|min:1|max:7',
            'session_duration_minutes' => 'required|integer|min:30|max:300'
        ]);

        Course::create(array_merge($request->only(['name', 'description', 'level', 'duration_weeks', 'total_fee',
            'sessions_per_week', 'session_duration_minutes']), ['teacher_per_student_amount' => $request->input('teacher_per_student_amount') ?? 0]));

        return redirect()->route('courses.index')
            ->with('success', 'Course created successfully!');
    }

    public function show(Course $course)
    {
        if (auth()->user()->isTeacher()) {
            abort_unless($course->batches()->where('teacher_id', auth()->id())->exists(), 403);
            $course = Course::select('id', 'name', 'level', 'description', 'duration_weeks')->findOrFail($course->id);
            $batches = $course->batches()->select('id', 'name', 'course_id', 'start_date', 'end_date', 'status', 'max_students')
                ->where('teacher_id', auth()->id())->with('schedules')->withCount('activeEnrollments')->paginate(15);

            return view('academic.batches', compact('course', 'batches'));
        }
        $batches = $course->batches()->with('teacher:id,name')->withCount('activeEnrollments')
            ->orderByRaw("CASE status WHEN 'ongoing' THEN 0 WHEN 'upcoming' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderByDesc('start_date')->get();

        return view('admin.courses.details', compact('course', 'batches'));
    }

    public function edit(Course $course)
    {
        return view('admin.courses.form', compact('course'));
    }

    public function update(Request $request, Course $course)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('courses', 'name')->where('level', $request->input('level'))->ignore($course->id)],
            'description' => 'required|string|max:1000',
            'level' => 'required|in:A1,A2,B1,B2,C1,C2',
            'duration_weeks' => 'required|integer|min:1|max:52',
            'total_fee' => 'required|decimal:0,2|min:0|max:99999999.99',
            'teacher_per_student_amount' => 'nullable|numeric|min:0',
            'sessions_per_week' => 'required|integer|min:1|max:7',
            'session_duration_minutes' => 'required|integer|min:30|max:300'
        ]);

        $course->update($request->only(['name', 'description', 'level', 'duration_weeks', 'total_fee',
            'sessions_per_week', 'session_duration_minutes']));

        return redirect()->route('courses.show', $course)
            ->with('success', 'Course updated successfully!');
    }

    public function destroy(Course $course)
    {
        $course->update(['is_active' => false]);
        return redirect()->route('courses.index')
            ->with('success', 'Course deactivated. Existing batches and history preserved.');
    }

    public function toggleStatus(Course $course)
    {
        $course->update([
            'is_active' => !$course->is_active
        ]);

        $status = $course->is_active ? 'activated' : 'deactivated';
        return redirect()->route('courses.show', $course)
            ->with('success', "Course {$status} successfully!");
    }

    // API methods for backward compatibility
    public function apiIndex(Request $request)
    {
        $this->authorize('viewAny', Course::class);
        
        $query = Course::query();
        
        if ($request->has('level')) {
            $query->where('level', $request->level);
        }
        
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        
        $courses = $query->with('batches')->paginate(10);
        
        return response()->json($courses);
    }

    public function apiShow(Course $course)
    {
        $this->authorize('view', $course);
        
        $course->load(['batches.teacher', 'batches.activeEnrollments']);
        
        return response()->json($course);
    }
}
