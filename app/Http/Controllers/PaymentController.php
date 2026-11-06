<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request, Enrollment $enrollment, PaymentService $service)
    {
        $data = $request->validate(['amount' => 'required|decimal:0,2|gt:0|max:99999999.99',
            'method' => 'required|in:cash,bank,card,jazzcash,easypaisa,online', 'reference' => 'nullable|string|max:255',
            'submission_key' => 'required|string|min:16|max:64']);
        $payment = $service->pay($enrollment->id, (string) $data['amount'], $data['method'],
            $data['submission_key'], $request->user(), $data['reference'] ?? null);

        $notifier = app(\App\Services\NotificationService::class);
        $amount = currency_format($data['amount']);
        $notifier->toUser($enrollment->student_id, [
            'type' => 'payment', 'icon' => 'fa-money-bill-wave', 'level' => 'success',
            'title' => 'Payment received',
            'body' => "We received your fee payment of {$amount}.",
            'url' => route('payments.show', $payment),
        ]);
        $notifier->toAdmins([
            'type' => 'payment', 'icon' => 'fa-money-bill-wave', 'level' => 'info',
            'title' => 'Fee payment recorded',
            'body' => ($enrollment->student->name ?? 'A student')." paid {$amount}.",
            'url' => route('payments.show', $payment),
        ], $request->user()->id);

        return redirect()->route('payments.show', $payment)->with('success', 'Payment recorded.');
    }

    public function show(Request $request, Payment $payment)
    {
        abort_unless($request->user()->isAdmin() || ($request->user()->isStudent()
            && $payment->enrollment->student_id === $request->user()->id), 403);
        $payment->load(['enrollment.student', 'enrollment.batch.course', 'allocations.installment', 'reversal']);

        return view('payments.receipt', compact('payment'));
    }

    public function refund(Request $request, \App\Models\FeeRefund $refund)
    {
        abort_unless($request->user()->isAdmin() || ($request->user()->isStudent()
            && (int) $refund->enrollment->student_id === (int) $request->user()->id), 403);

        return view('payments.refund', compact('refund'));
    }

    public function reverse(Request $request, Payment $payment, PaymentService $service)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        $service->reverse($payment, $request->user(), $data['reason']);

        return redirect()->route('payments.show', $payment)->with('success', 'Payment reversed. Original receipt retained.');
    }
}
