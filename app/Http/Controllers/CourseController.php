<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Batch;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher')->only(['index', 'show']);
        $this->middleware('role:admin')->except(['index', 'show']);
    }

    public function index()
    {
        $courses = Course::withCount(['batches', 'batches as active_batches_count' => function($q) {
            $q->where('status', 'active');
        }])->orderBy('level')->get();

        $stats = [
            'total_courses' => $courses->count(),
            'active_batches' => Batch::where('status', 'ongoing')->count(),
            'total_students' => Batch::withCount('enrollments')->get()->sum('enrollments_count'),
            'course_levels' => $courses->groupBy('level')->count()
        ];

        return view('admin.courses.index', compact('courses', 'stats'));
    }

    public function create()
    {
        return view('admin.courses.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'level' => 'required|in:A1,A2,B1,B2,C1,C2',
            'duration_weeks' => 'required|integer|min:1|max:52',
            'total_fee' => 'required|numeric|min:0',
            'max_students' => 'required|integer|min:1|max:50',
            'teacher_per_student_amount' => 'required|numeric|min:0',
            'sessions_per_week' => 'required|integer|min:1|max:7',
            'session_duration_minutes' => 'required|integer|min:30|max:300'
        ]);

        Course::create($request->all());

        return redirect()->route('courses.index')
            ->with('success', 'Course created successfully!');
    }

    public function show(Course $course)
    {
        $course->load(['batches.enrollments.student', 'batches.teacher']);
        
        $stats = [
            'total_batches' => $course->batches()->count(),
            'active_batches' => $course->batches()->where('status', 'ongoing')->count(),
            'total_students' => $course->batches()->withCount('enrollments')->get()->sum('enrollments_count'),
            'average_batch_size' => $course->batches()->withCount('enrollments')->get()->avg('enrollments_count')
        ];

        return view('admin.courses.show', compact('course', 'stats'));
    }

    public function edit(Course $course)
    {
        return view('admin.courses.edit', compact('course'));
    }

    public function update(Request $request, Course $course)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'level' => 'required|in:A1,A2,B1,B2,C1,C2',
            'duration_weeks' => 'required|integer|min:1|max:52',
            'total_fee' => 'required|numeric|min:0',
            'max_students' => 'required|integer|min:1|max:50',
            'teacher_per_student_amount' => 'required|numeric|min:0',
            'sessions_per_week' => 'required|integer|min:1|max:7',
            'session_duration_minutes' => 'required|integer|min:30|max:300'
        ]);

        $course->update($request->all());

        return redirect()->route('courses.show', $course)
            ->with('success', 'Course updated successfully!');
    }

    public function destroy(Course $course)
    {
        if ($course->batches()->where('status', 'ongoing')->exists()) {
            return back()->withErrors(['error' => 'Cannot delete course with active batches.']);
        }

        $course->delete();
        return redirect()->route('courses.index')
            ->with('success', 'Course deleted successfully!');
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