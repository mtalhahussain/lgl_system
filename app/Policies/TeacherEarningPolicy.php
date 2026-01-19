<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TeacherEarning;

class TeacherEarningPolicy
{
    /**
     * Determine whether the user can view any teacher earnings.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'accountant']);
    }

    /**
     * Determine whether the user can view the teacher earning.
     */
    public function view(User $user, TeacherEarning $earning): bool
    {
        // Admin and accountant can view all earnings
        if (in_array($user->role, ['admin', 'accountant'])) {
            return true;
        }

        // Teachers can view their own earnings
        if ($user->role === 'teacher') {
            return $earning->teacher_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create teacher earnings.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can update the teacher earning.
     */
    public function update(User $user, TeacherEarning $earning): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can delete the teacher earning.
     */
    public function delete(User $user, TeacherEarning $earning): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can process payments for teacher earnings.
     */
    public function processPayment(User $user, TeacherEarning $earning): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can generate salary reports.
     */
    public function generateReports(User $user): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can view detailed salary breakdowns.
     */
    public function viewDetailedBreakdown(User $user, TeacherEarning $earning): bool
    {
        // Admin and accountant can view detailed breakdowns
        if (in_array($user->role, ['admin', 'accountant'])) {
            return true;
        }

        // Teachers can view their own detailed breakdowns
        if ($user->role === 'teacher') {
            return $earning->teacher_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can recalculate earnings.
     */
    public function recalculate(User $user, TeacherEarning $earning): bool
    {
        return $user->role === 'admin';
    }
}