<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Batch::class);
        
        $query = Batch::with(['course', 'teacher']);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by course
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }
        
        // Filter by teacher (for teacher role)
        if (auth()->user()->role === 'teacher') {
            $query->where('teacher_id', auth()->id());
        }
        
        $batches = $query->paginate(10);
        
        // Add enrollment count to each batch
        $batches->getCollection()->transform(function ($batch) {
            $batch->current_enrollment_count = $batch->getCurrentEnrollmentCount();
            $batch->available_spots = $batch->available_spots;
            return $batch;
        });
        
        return response()->json($batches);
    }

    public function show(Batch $batch)
    {
        $this->authorize('view', $batch);
        
        $batch->load([
            'course',
            'teacher',
            'activeEnrollments.student',
            'classeSessions' => function ($query) {
                $query->orderBy('session_date', 'desc')->limit(5);
            }
        ]);
        
        $batch->current_enrollment_count = $batch->getCurrentEnrollmentCount();
        $batch->available_spots = $batch->available_spots;
        
        return response()->json($batch);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Batch::class);
        
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'teacher_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255|unique:batches',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'max_students' => 'required|integer|min:1|max:50',
            'meeting_platform' => 'required|in:zoom,google_meet,in_person',
            'meeting_link' => 'nullable|url',
            'meeting_password' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);
        
        // Validate teacher
        $teacher = User::findOrFail($validated['teacher_id']);
        if ($teacher->role !== 'teacher') {
            return response()->json([
                'message' => 'Selected user is not a teacher'
            ], 422);
        }
        
        $batch = Batch::create($validated);
        $batch->load(['course', 'teacher']);
        
        return response()->json($batch, 201);
    }

    public function update(Request $request, Batch $batch)
    {
        $this->authorize('update', $batch);
        
        $validated = $request->validate([
            'course_id' => 'exists:courses,id',
            'teacher_id' => 'exists:users,id',
            'name' => 'string|max:255|unique:batches,name,' . $batch->id,
            'start_date' => 'date',
            'end_date' => 'nullable|date|after:start_date',
            'max_students' => 'integer|min:1|max:50',
            'status' => 'in:upcoming,ongoing,completed,cancelled',
            'meeting_platform' => 'in:zoom,google_meet,in_person',
            'meeting_link' => 'nullable|url',
            'meeting_password' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);
        
        // Validate teacher if provided
        if (isset($validated['teacher_id'])) {
            $teacher = User::findOrFail($validated['teacher_id']);
            if ($teacher->role !== 'teacher') {
                return response()->json([
                    'message' => 'Selected user is not a teacher'
                ], 422);
            }
        }
        
        // Prevent reducing max_students below current enrollment
        if (isset($validated['max_students'])) {
            $currentEnrollments = $batch->getCurrentEnrollmentCount();
            if ($validated['max_students'] < $currentEnrollments) {
                return response()->json([
                    'message' => 'Cannot reduce max students below current enrollment count'
                ], 422);
            }
        }
        
        $batch->update($validated);
        $batch->load(['course', 'teacher']);
        
        return response()->json($batch);
    }

    public function destroy(Batch $batch)
    {
        $this->authorize('delete', $batch);
        
        // Check if batch has active enrollments
        if ($batch->activeEnrollments()->exists()) {
            return response()->json([
                'message' => 'Cannot delete batch with active enrollments'
            ], 422);
        }
        
        $batch->delete();
        
        return response()->json(['message' => 'Batch deleted successfully']);
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