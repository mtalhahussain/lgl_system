<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\FeeRefund;
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
    public const REFUND_METHODS = ['cash' => 'Cash', 'bank' => 'Bank', 'jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa'];

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
            if ($enrollment->refunds()->exists()) {
                throw ValidationException::withMessages(['payment' => 'This enrollment has a refund, so its payments cannot be reversed.']);
            }
            $this->assertReconciled($enrollment);
            $reversal = PaymentReversal::create(['payment_id' => $payment->id, 'reversed_by' => $actor->id, 'reason' => trim($reason), 'created_at' => now()]);
            $this->refreshBalances($enrollment);
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'payment.reversed', 'subject_type' => 'payment',
                'subject_id' => $payment->id, 'reason' => trim($reason), 'changes' => ['reversal_id' => $reversal->id]]);

            return $reversal;
        }, 3);
    }

    /**
     * Changes the discount and moves the difference through the unpaid part of the schedule only:
     * more discount trims the latest installments' outstanding amounts; less discount is added to
     * the latest unpaid installment (or the last one). Paid amounts never change.
     */
    public function changeDiscount(Enrollment $enrollment, string $percentage, User $actor, string $reason, ?array $refund = null): Enrollment
    {
        abort_unless($actor->isAdmin() && $actor->is_active, 403);
        if (strlen(trim($reason)) < 5 || strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Provide a reason of 5–1000 characters.']);
        }
        $basisPoints = Money::minor($percentage);
        if ($basisPoints > 10000) {
            throw ValidationException::withMessages(['discount_percentage' => 'Discount cannot exceed 100%.']);
        }

        $payload = hash('sha256', json_encode([$enrollment->id, $basisPoints, trim($reason), $refund['method'] ?? null, $refund['reference'] ?? null, $actor->id]));

        return DB::transaction(function () use ($enrollment, $basisPoints, $actor, $reason, $refund, $payload) {
            $enrollment = Enrollment::lockForUpdate()->findOrFail($enrollment->id);
            // A repeated submission of the same refund returns the already-applied result.
            if (! empty($refund['submission_key']) && ($existing = FeeRefund::where('submission_key', $refund['submission_key'])->first())) {
                if (! hash_equals($existing->payload_hash, $payload) || (int) $existing->enrollment_id !== (int) $enrollment->id) {
                    throw ValidationException::withMessages(['submission_key' => 'Submission key was already used for different refund details.']);
                }

                return $enrollment;
            }
            if ($enrollment->status === 'transferred') {
                throw ValidationException::withMessages(['discount_percentage' => 'Change the discount on the enrollment it was transferred to.']);
            }
            $this->assertReconciled($enrollment);
            $gross = Money::minor($enrollment->total_fee);
            $oldDiscount = Money::minor($enrollment->discount_amount);
            $newDiscount = intdiv($gross * $basisPoints + 5000, 10000);
            if ($newDiscount === $oldDiscount) {
                throw ValidationException::withMessages(['discount_percentage' => 'The discount is unchanged.']);
            }
            $kept = Money::minor($enrollment->paid_amount) - Money::minor($enrollment->refunded_amount ?? '0');
            $refundMinor = max(0, $kept - ($gross - $newDiscount));
            if ($refundMinor > 0 && empty($refund['method'])) {
                // Highest % whose rounded discount still leaves the net at or above what the student has paid.
                $maxBasisPoints = intdiv(($gross - $kept) * 10000, max(1, $gross));
                while ($maxBasisPoints > 0 && intdiv($gross * $maxBasisPoints + 5000, 10000) > $gross - $kept) {
                    $maxBasisPoints--;
                }
                $max = rtrim(rtrim(intdiv($maxBasisPoints, 100).'.'.str_pad((string) ($maxBasisPoints % 100), 2, '0', STR_PAD_LEFT), '0'), '.');
                throw ValidationException::withMessages(['refund_method' => 'This student has already paid '.currency_format(Money::decimal($kept))
                    .'. This discount needs a refund of '.currency_format(Money::decimal($refundMinor)).': choose how it was refunded, or keep the discount at '.$max.'% or less.']);
            }
            if ($refundMinor > 0 && (! array_key_exists($refund['method'], self::REFUND_METHODS)
                || ! preg_match('/^[A-Za-z0-9_-]{16,64}$/D', (string) ($refund['submission_key'] ?? '')))) {
                throw ValidationException::withMessages(['refund_method' => 'Choose a supported refund method.']);
            }
            $installments = $enrollment->feeInstallments()->orderByDesc('due_date')->orderByDesc('id')->lockForUpdate()->get();
            $changes = [];
            $adjust = function ($installment, int $delta) use (&$changes) {
                $before = $installment->amount;
                $installment->update(['amount' => Money::decimal(Money::minor($installment->amount) + $delta)]);
                $changes[] = ['installment_id' => $installment->id, 'before' => $before, 'after' => $installment->amount];
            };
            if ($newDiscount > $oldDiscount) {
                $remaining = $newDiscount - $oldDiscount;
                foreach ($installments as $installment) {
                    $cut = min($remaining, Money::minor($installment->outstanding_amount));
                    if ($cut > 0) {
                        $adjust($installment, -$cut);
                        $remaining -= $cut;
                    }
                    if ($remaining === 0) {
                        break;
                    }
                }
                // Whatever the unpaid schedule could not absorb is the refund; paid installments stay as they are.
                if ($remaining !== $refundMinor) {
                    throw ValidationException::withMessages(['enrollment' => 'This enrollment needs balance reconciliation before changing the discount.']);
                }
            } else {
                $target = $installments->first(fn ($row) => Money::minor($row->outstanding_amount) > 0) ?? $installments->first();
                if (! $target) {
                    throw ValidationException::withMessages(['discount_percentage' => 'Create a fee schedule before changing the discount.']);
                }
                $adjust($target, $oldDiscount - $newDiscount);
            }
            $before = $enrollment->discount_amount;
            $enrollment->update(['discount_amount' => Money::decimal($newDiscount)]);
            $feeRefund = null;
            if ($refundMinor > 0) {
                $enrollment->loadMissing(['student', 'batch.course']);
                $feeRefund = FeeRefund::create(['enrollment_id' => $enrollment->id, 'amount' => Money::decimal($refundMinor),
                    'method' => $refund['method'], 'reference' => $refund['reference'] ?? null, 'reason' => trim($reason),
                    'refunded_at' => now(), 'refunded_by' => $actor->id, 'voucher_number' => 'RFD-'.Str::ulid(),
                    'submission_key' => $refund['submission_key'], 'payload_hash' => $payload, 'created_at' => now(),
                    'slip_details' => ['student_name' => $enrollment->student->name, 'student_reference' => $enrollment->student->student_id,
                        'batch_name' => $enrollment->batch->name, 'course_name' => $enrollment->batch->course->name,
                        'discount_percentage' => $enrollment->discount_percentage, 'institute_name' => institute_name(),
                        'currency_code' => \App\Models\Setting::get('currency_code', 'PKR'), 'timezone' => config('app.timezone')]]);
                $enrollment->update(['refunded_amount' => Money::decimal(Money::minor($enrollment->refunded_amount ?? '0') + $refundMinor)]);
            }
            $this->refreshBalances($enrollment);
            $this->assertReconciled($enrollment);
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'enrollment.discount_changed', 'subject_type' => 'enrollment',
                'subject_id' => $enrollment->id, 'reason' => trim($reason),
                'changes' => ['before' => ['discount_amount' => $before], 'after' => ['discount_amount' => $enrollment->discount_amount,
                    'discount_percentage' => $enrollment->discount_percentage], 'installments' => $changes,
                    'refund' => $feeRefund ? ['id' => $feeRefund->id, 'amount' => $feeRefund->amount, 'voucher_number' => $feeRefund->voucher_number] : null]]);

            return $enrollment;
        }, 3);
    }

    public function assertReconciled(Enrollment $enrollment): void
    {
        $payments = $enrollment->payments()->posted()->with('allocations')->get();
        $paid = $payments->sum(fn ($payment) => Money::minor($payment->amount));
        $invalidPayment = $payments->contains(fn ($payment) => $payment->allocations->sum(fn ($row) => Money::minor($row->amount)) !== Money::minor($payment->amount));
        $scheduled = $enrollment->feeInstallments()->pluck('amount')->sum(fn ($value) => Money::minor($value));
        $net = Money::minor($enrollment->total_fee) - Money::minor($enrollment->discount_amount);
        // Refunds leave paid installments intact, so the schedule covers the net fee plus refunded amounts.
        $refunded = $enrollment->refunds()->pluck('amount')->sum(fn ($value) => Money::minor($value));
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
        if ($paid !== Money::minor($enrollment->paid_amount) || $refunded !== Money::minor($enrollment->refunded_amount ?? '0')
            || $scheduled !== $net + $refunded || $paid > $net + $refunded || $allocated !== $paid || $invalidAllocation || $invalidPayment) {
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
