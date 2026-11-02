<?php

namespace App\Services;

use App\Models\FeeInstallment;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Support\Money;

class OverviewService
{
    // Admin reminders: batches starting or ending within the next $days days (today included).
    public function batchReminders(int $days = 2): array
    {
        $today = today();
        $until = $today->copy()->addDays($days);
        $query = fn (string $status, string $column) => \App\Models\Batch::with(['course:id,name', 'teacher:id,name'])
            ->select('id', 'name', 'course_id', 'teacher_id', 'start_date', 'end_date', 'status')
            ->where('status', $status)->whereDate($column, '>=', $today)->whereDate($column, '<=', $until)
            ->orderBy($column)->orderBy('id')->get();

        return ['starting' => $query('upcoming', 'start_date'), 'ending' => $query('ongoing', 'end_date')];
    }

    public function financialTotals(): array
    {
        $posted = Money::minor((string) Payment::posted()->sum('amount'));
        $refunded = Money::minor((string) \App\Models\FeeRefund::sum('amount'));
        $monthRefunded = Money::minor((string) \App\Models\FeeRefund::whereBetween('refunded_at', [today()->startOfMonth(), now()])->sum('amount'));
        $scheduled = Money::minor((string) FeeInstallment::sum('amount'));
        $month = Money::minor((string) Payment::whereBetween('paid_at', [today()->startOfMonth(), now()])->sum('amount'));
        $reversed = PaymentReversal::with('payment')->whereBetween('created_at', [today()->startOfMonth(), now()])->get()
            ->sum(fn ($row) => Money::minor($row->payment->amount));
        $allocations = \App\Models\PaymentAllocation::query()->selectRaw('fee_installment_id, SUM(payment_allocations.amount) AS paid')
            ->whereHas('payment', fn ($query) => $query->posted())->groupBy('fee_installment_id');
        $overdueAmount = \Illuminate\Support\Facades\DB::table('fee_installments')
            ->leftJoinSub($allocations, 'allocated', 'allocated.fee_installment_id', '=', 'fee_installments.id')
            ->where('due_date', '<', today()->toDateString())
            ->selectRaw('ROUND(COALESCE(SUM(CASE WHEN fee_installments.amount > COALESCE(allocated.paid, 0)
                THEN fee_installments.amount - COALESCE(allocated.paid, 0) ELSE 0 END), 0), 2) AS outstanding')->value('outstanding');
        $overdue = Money::minor((string) $overdueAmount);

        // The schedule still covers refunded amounts (scheduled = net + refunded), so outstanding stays scheduled − posted.
        $monthNet = $month - $reversed - $monthRefunded;

        return ['Net collected' => Money::decimal(max(0, $posted - $refunded)), 'Outstanding' => Money::decimal(max(0, $scheduled - $posted)),
            'Overdue' => Money::decimal($overdue), 'This month net collected' => ($monthNet < 0 ? '-' : '').Money::decimal(abs($monthNet))];
    }

    /** Outstanding and overdue scheduled balances for the enrollments matched by an (already scoped) query. */
    public function debtTotals(\Illuminate\Database\Eloquent\Builder $enrollments): array
    {
        return $this->remainingTotals('fee_installments.enrollment_id', (clone $enrollments)->reorder()->select('enrollments.id'));
    }

    /** Outstanding and overdue amounts of the installments matched by an (already scoped) query. */
    public function installmentTotals(\Illuminate\Database\Eloquent\Builder $installments): array
    {
        return $this->remainingTotals('fee_installments.id', (clone $installments)->reorder()->select('fee_installments.id'));
    }

    private function remainingTotals(string $column, \Illuminate\Database\Eloquent\Builder $ids): array
    {
        $allocations = \App\Models\PaymentAllocation::query()->selectRaw('fee_installment_id, SUM(payment_allocations.amount) AS paid')
            ->whereHas('payment', fn ($query) => $query->posted())->groupBy('fee_installment_id');
        $remaining = 'CASE WHEN fee_installments.amount > COALESCE(allocated.paid, 0) THEN fee_installments.amount - COALESCE(allocated.paid, 0) ELSE 0 END';
        $row = \Illuminate\Support\Facades\DB::table('fee_installments')
            ->leftJoinSub($allocations, 'allocated', 'allocated.fee_installment_id', '=', 'fee_installments.id')
            ->whereIn($column, $ids)
            ->selectRaw("ROUND(COALESCE(SUM({$remaining}), 0), 2) AS outstanding,
                ROUND(COALESCE(SUM(CASE WHEN fee_installments.due_date < ? THEN {$remaining} ELSE 0 END), 0), 2) AS overdue", [today()->toDateString()])
            ->first();

        return ['outstanding' => Money::decimal(Money::minor((string) $row->outstanding)), 'overdue' => Money::decimal(Money::minor((string) $row->overdue))];
    }
}
