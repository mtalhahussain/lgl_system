<?php

namespace App\Policies;

use App\Models\User;
use App\Models\FeeInstallment;

class FeeInstallmentPolicy
{
    /**
     * Determine whether the user can view any fee installments.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can view the fee installment.
     */
    public function view(User $user, FeeInstallment $installment): bool
    {
        // Admin and accountant can view all installments
        if (in_array($user->role, ['admin', 'accountant'])) {
            return true;
        }

        // Students can view their own installments
        if ($user->role === 'student') {
            return $installment->enrollment->student_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create fee installments.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can update the fee installment.
     */
    public function update(User $user, FeeInstallment $installment): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can delete the fee installment.
     */
    public function delete(User $user, FeeInstallment $installment): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can process payments for the installment.
     */
    public function processPayment(User $user, FeeInstallment $installment): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can mark the installment as paid.
     */
    public function markPaid(User $user, FeeInstallment $installment): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can cancel the installment.
     */
    public function cancel(User $user, FeeInstallment $installment): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }

    /**
     * Determine whether the user can modify due dates.
     */
    public function modifyDueDate(User $user, FeeInstallment $installment): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can generate payment reminders.
     */
    public function generateReminders(User $user): bool
    {
        return in_array($user->role, ['admin', 'accountant']);
    }
}