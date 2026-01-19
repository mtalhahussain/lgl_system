<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
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

    public function show(Course $course)
    {
        $this->authorize('view', $course);
        
        $course->load(['batches.teacher', 'batches.activeEnrollments']);
        
        return response()->json($course);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Course::class);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'level' => 'required|in:A1,A2,B1,B2,C1,C2',
            'description' => 'nullable|string',
            'total_fee' => 'required|numeric|min:0',
            'teacher_per_student_amount' => 'required|numeric|min:0',
            'duration_weeks' => 'required|integer|min:1',
            'sessions_per_week' => 'required|integer|min:1|max:7',
            'session_duration_minutes' => 'required|integer|min:30|max:300',
            'is_active' => 'boolean',
        ]);
        
        $course = Course::create($validated);
        
        return response()->json($course, 201);
    }

    public function update(Request $request, Course $course)
    {
        $this->authorize('update', $course);
        
        $validated = $request->validate([
            'name' => 'string|max:255',
            'level' => 'in:A1,A2,B1,B2,C1,C2',
            'description' => 'nullable|string',
            'total_fee' => 'numeric|min:0',
            'teacher_per_student_amount' => 'numeric|min:0',
            'duration_weeks' => 'integer|min:1',
            'sessions_per_week' => 'integer|min:1|max:7',
            'session_duration_minutes' => 'integer|min:30|max:300',
            'is_active' => 'boolean',
        ]);
        
        $course->update($validated);
        
        return response()->json($course);
    }

    public function destroy(Course $course)
    {
        $this->authorize('delete', $course);
        
        // Check if course has active batches
        if ($course->batches()->whereIn('status', ['upcoming', 'ongoing'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete course with active batches'
            ], 422);
        }
        
        $course->delete();
        
        return response()->json(['message' => 'Course deleted successfully']);
    }
}