<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'accountant']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Admin can view all users
        if ($user->role === 'admin') {
            return true;
        }

        // Users can view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Teachers can view their students
        if ($user->role === 'teacher' && $model->role === 'student') {
            return $user->teachingBatches()
                ->whereHas('activeEnrollments', function ($query) use ($model) {
                    $query->where('student_id', $model->id);
                })
                ->exists();
        }

        // Accountants can view students for fee management
        if ($user->role === 'accountant' && $model->role === 'student') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Admin can update all users
        if ($user->role === 'admin') {
            return true;
        }

        // Users can update their own basic profile
        if ($user->id === $model->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update role of the model.
     */
    public function updateRole(User $user, User $model): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Admin can delete users (except themselves)
        return $user->role === 'admin' && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can deactivate the model.
     */
    public function deactivate(User $user, User $model): bool
    {
        // Admin can deactivate users (except themselves)
        return $user->role === 'admin' && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can view financial information of the model.
     */
    public function viewFinancials(User $user, User $model): bool
    {
        // Admin and accountant can view financial info
        if (in_array($user->role, ['admin', 'accountant'])) {
            return true;
        }

        // Users can view their own financial info
        if ($user->id === $model->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can assign batches to teachers.
     */
    public function assignBatches(User $user, User $teacher): bool
    {
        return $user->role === 'admin' && $teacher->role === 'teacher';
    }

    /**
     * Determine whether the user can view reports about the model.
     */
    public function viewReports(User $user, User $model): bool
    {
        // Admin can view all reports
        if ($user->role === 'admin') {
            return true;
        }

        // Accountant can view student financial reports
        if ($user->role === 'accountant' && $model->role === 'student') {
            return true;
        }

        // Teachers can view reports for their own students
        if ($user->role === 'teacher' && $model->role === 'student') {
            return $user->teachingBatches()
                ->whereHas('activeEnrollments', function ($query) use ($model) {
                    $query->where('student_id', $model->id);
                })
                ->exists();
        }

        // Users can view their own reports
        if ($user->id === $model->id) {
            return true;
        }

        return false;
    }
}