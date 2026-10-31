<?php

namespace App\Services;

use App\Models\FeeInstallment;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Support\Money;

class OverviewService
{
    public function financialTotals(): array
    {
        $posted = Money::minor((string) Payment::posted()->sum('amount'));
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

        return ['Net collected' => Money::decimal($posted), 'Outstanding' => Money::decimal(max(0, $scheduled - $posted)),
            'Overdue' => Money::decimal($overdue), 'This month net collected' => ($month < $reversed ? '-' : '').Money::decimal(abs($month - $reversed))];
    }
}
