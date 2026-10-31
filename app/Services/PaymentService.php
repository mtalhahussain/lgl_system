<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentReversal;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function pay(int $enrollmentId, string $amount, string $method, string $key, User $actor, ?string $reference = null, ?int $installmentId = null): Payment
    {
        abort_unless($actor->isAdmin() && $actor->is_active, 403);
        $minor = Money::minor($amount);
        if ($minor <= 0 || ! in_array($method, ['cash', 'bank', 'card', 'jazzcash', 'easypaisa', 'online'], true)
            || ! preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $key)) {
            throw ValidationException::withMessages(['amount' => 'A positive amount, supported method and valid submission key are required.']);
        }
        $payload = hash('sha256', json_encode([$enrollmentId, $minor, $method, $reference, $installmentId, $actor->id]));

        try {
            return DB::transaction(function () use ($enrollmentId, $minor, $method, $key, $actor, $reference, $installmentId, $payload) {
                $enrollment = Enrollment::lockForUpdate()->findOrFail($enrollmentId);
                if ($enrollment->status === 'transferred') {
                    throw ValidationException::withMessages(['enrollment' => 'Record payments against the transferred enrollment.']);
                }
                $existing = Payment::where('submission_key', $key)->first();
                if ($existing) {
                    if (! hash_equals($existing->payload_hash, $payload)) {
                        throw ValidationException::withMessages(['submission_key' => 'Submission key was already used for different payment details.']);
                    }

                    return $existing;
                }
                $this->assertReconciled($enrollment);
                $installments = $enrollment->feeInstallments()->orderBy('due_date')->orderBy('id')->lockForUpdate()->get();
                if ($installmentId !== null) {
                    $installments = $installments->where('id', $installmentId);
                }
                $available = $installments->sum(fn ($row) => Money::minor($row->outstanding_amount));
                if ($minor > $available) {
                    throw ValidationException::withMessages(['amount' => 'Payment exceeds the outstanding scheduled amount.']);
                }
                $payment = Payment::create(['enrollment_id' => $enrollment->id, 'amount' => Money::decimal($minor),
                    'method' => $method, 'reference' => $reference, 'paid_at' => now(), 'received_by' => $actor->id,
                    'receipt_number' => 'LGL-'.Str::ulid(), 'submission_key' => $key, 'payload_hash' => $payload, 'created_at' => now(),
                    'receipt_details' => ['student_name' => $enrollment->student->name, 'student_reference' => $enrollment->student->student_id,
                        'batch_name' => $enrollment->batch->name, 'course_name' => $enrollment->batch->course->name,
                        'institute_name' => institute_name(), 'currency_code' => \App\Models\Setting::get('currency_code', 'PKR'),
                        'timezone' => config('app.timezone')]]);
                $remaining = $minor;
                foreach ($installments as $installment) {
                    if ($remaining === 0) {
                        break;
                    }
                    $allocated = min($remaining, Money::minor($installment->outstanding_amount));
                    if ($allocated === 0) {
                        continue;
                    }
                    PaymentAllocation::create(['payment_id' => $payment->id, 'fee_installment_id' => $installment->id, 'amount' => Money::decimal($allocated)]);
                    $remaining -= $allocated;
                }
                $this->refreshBalances($enrollment);
                AuditLog::create(['actor_id' => $actor->id, 'action' => 'payment.posted', 'subject_type' => 'payment',
                    'subject_id' => $payment->id, 'changes' => ['amount' => $payment->amount, 'receipt_number' => $payment->receipt_number]]);

                return $payment;
            }, 3);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            $existing = Payment::where('submission_key', $key)->first();
            if (! $existing) {
                throw $exception;
            }
            if (! hash_equals($existing->payload_hash, $payload)) {
                throw ValidationException::withMessages(['submission_key' => 'Submission key was already used for different payment details.']);
            }

            return $existing;
        }
    }

    public function reverse(Payment $payment, User $actor, string $reason): PaymentReversal
    {
        abort_unless($actor->isAdmin() && $actor->is_active, 403);
        if (strlen(trim($reason)) < 5 || strlen($reason) > 1000 || $payment->legacy_source !== null) {
            throw ValidationException::withMessages(['reason' => 'Provide a reason of 5–1000 characters. Imported opening balances need separate reconciliation.']);
        }

        return DB::transaction(function () use ($payment, $actor, $reason) {
            $enrollment = Enrollment::lockForUpdate()->findOrFail($payment->enrollment_id);
            if (PaymentReversal::where('payment_id', $payment->id)->exists()) {
                throw ValidationException::withMessages(['payment' => 'Payment was already reversed.']);
            }
            $this->assertReconciled($enrollment);
            $reversal = PaymentReversal::create(['payment_id' => $payment->id, 'reversed_by' => $actor->id, 'reason' => trim($reason), 'created_at' => now()]);
            $this->refreshBalances($enrollment);
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'payment.reversed', 'subject_type' => 'payment',
                'subject_id' => $payment->id, 'reason' => trim($reason), 'changes' => ['reversal_id' => $reversal->id]]);

            return $reversal;
        }, 3);
    }

    public function assertReconciled(Enrollment $enrollment): void
    {
        $payments = $enrollment->payments()->posted()->with('allocations')->get();
        $paid = $payments->sum(fn ($payment) => Money::minor($payment->amount));
        $invalidPayment = $payments->contains(fn ($payment) => $payment->allocations->sum(fn ($row) => Money::minor($row->amount)) !== Money::minor($payment->amount));
        $scheduled = $enrollment->feeInstallments()->pluck('amount')->sum(fn ($value) => Money::minor($value));
        $net = Money::minor($enrollment->total_fee) - Money::minor($enrollment->discount_amount);
        $allocated = 0;
        $invalidAllocation = false;
        foreach ($enrollment->feeInstallments()->get() as $installment) {
            $rowPaid = $installment->allocations()->whereHas('payment', fn ($query) => $query->posted())->get();
            $total = $rowPaid->sum(fn ($row) => Money::minor($row->amount));
            $allocated += $total;
            if ($total > Money::minor($installment->amount)
                || $rowPaid->contains(fn ($row) => $row->payment->enrollment_id !== $enrollment->id)) {
                $invalidAllocation = true;
            }
        }
        if ($paid !== Money::minor($enrollment->paid_amount) || $scheduled !== $net || $paid > $net || $allocated !== $paid || $invalidAllocation || $invalidPayment) {
            throw ValidationException::withMessages(['enrollment' => 'This enrollment needs balance reconciliation before collecting payments.']);
        }
    }

    private function refreshBalances(Enrollment $enrollment): void
    {
        $paid = $enrollment->payments()->posted()->pluck('amount')->sum(fn ($value) => Money::minor($value));
        $enrollment->update(['paid_amount' => Money::decimal($paid)]);
        foreach ($enrollment->feeInstallments()->get() as $installment) {
            $outstanding = Money::minor($installment->outstanding_amount);
            $installment->update(['status' => $outstanding === 0 ? 'paid' : 'pending',
                'paid_date' => $outstanding === 0 ? ($installment->paid_date ?? today()) : null]);
        }
    }
}
