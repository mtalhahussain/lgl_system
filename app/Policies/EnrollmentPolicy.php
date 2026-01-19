<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Enrollment;

class EnrollmentPolicy
{
    /**
     * Determine whether the user can view any enrollments.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'accountant']);
    }

    /**
     * Determine whether the user can view the enrollment.
     */
    public function view(User $user, Enrollment $enrollment): bool
    {
        // Admin and accountant can view all enrollments
        if (in_array($user->role, ['admin', 'accountant'])) {
            return true;
        }

        // Teachers can view enrollments for their batches
        if ($user->role === 'teacher') {
            return $enrollment->batch->teacher_id === $user->id;
        }

        // Students can view their own enrollments
        if ($user->role === 'student') {
            return $enrollment->student_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create enrollments.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can update the enrollment.
     */
    public function update(User $user, Enrollment $enrollment): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can delete the enrollment.
     */
    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can transfer the enrollment.
     */
    public function transfer(User $user, Enrollment $enrollment): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can drop the enrollment.
     */
    public function drop(User $user, Enrollment $enrollment): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can view financial details of the enrollment.
     */
    public function viewFinancials(User $user, Enrollment $enrollment): bool
    {
        // Admin and accountant can view financial details
        if (in_array($user->role, ['admin', 'accountant'])) {
            return true;
        }

        // Students can view their own payment details
        if ($user->role === 'student') {
            return $enrollment->student_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can manage payments for the enrollment.
     */
    public function managePayments(User $user, Enrollment $enrollment): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can apply discounts to the enrollment.
     */
    public function applyDiscount(User $user, Enrollment $enrollment): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can complete the enrollment.
     */
    public function complete(User $user, Enrollment $enrollment): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }
}