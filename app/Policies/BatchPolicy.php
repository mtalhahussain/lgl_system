<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Batch;

class BatchPolicy
{
    /**
     * Determine whether the user can view any batches.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'accountant']);
    }

    /**
     * Determine whether the user can view the batch.
     */
    public function view(User $user, Batch $batch): bool
    {
        // Admin and accountant can view all batches
        if (in_array($user->role, ['admin', 'accountant'])) {
            return true;
        }

        // Teachers can only view their own batches
        if ($user->role === 'teacher') {
            return $batch->teacher_id === $user->id;
        }

        // Students can view batches they're enrolled in
        if ($user->role === 'student') {
            return $batch->activeEnrollments()
                ->where('student_id', $user->id)
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create batches.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the batch.
     */
    public function update(User $user, Batch $batch): bool
    {
        // Only admin can update batch details
        if ($user->role === 'admin') {
            return true;
        }

        // Teachers can update their own batch sessions and materials
        if ($user->role === 'teacher') {
            return $batch->teacher_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the batch.
     */
    public function delete(User $user, Batch $batch): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can manage enrollments for the batch.
     */
    public function manageEnrollments(User $user, Batch $batch): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can view batch financial data.
     */
    public function viewFinancials(User $user, Batch $batch): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can mark attendance for the batch.
     */
    public function markAttendance(User $user, Batch $batch): bool
    {
        // Admin can mark attendance for any batch
        if ($user->role === 'admin') {
            return true;
        }

        // Teachers can mark attendance for their own batches
        if ($user->role === 'teacher') {
            return $batch->teacher_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can view attendance for the batch.
     */
    public function viewAttendance(User $user, Batch $batch): bool
    {
        // Admin can view attendance for any batch
        if ($user->role === 'admin') {
            return true;
        }

        // Teachers can view attendance for their own batches
        if ($user->role === 'teacher') {
            return $batch->teacher_id === $user->id;
        }

        // Students can view their own attendance
        if ($user->role === 'student') {
            return $batch->activeEnrollments()
                ->where('student_id', $user->id)
                ->exists();
        }

        return false;
    }
}