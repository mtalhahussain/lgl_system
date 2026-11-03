<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\FeeInstallment;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/** WhatsApp fee reminders: automatic (before/on due date, once each) and Admin manual sends. */
class FeeReminderService
{
    public function __construct(private WhatsAppService $whatsApp) {}

    public function settings(): array
    {
        return [
            'enabled' => Setting::get('whatsapp_reminders_enabled', '0') === '1',
            'days_before' => max(0, min(30, (int) Setting::get('whatsapp_days_before', 3))),
            'on_due_date' => Setting::get('whatsapp_send_on_due_date', '1') === '1',
            'template' => (string) Setting::get('whatsapp_template_name', 'fee_reminder'),
            'language' => (string) Setting::get('whatsapp_template_language', 'en'),
        ];
    }

    /** Daily run. @return array{sent: int, failed: int, skipped: int} */
    public function sendDue(): array
    {
        $settings = $this->settings();
        $totals = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $targets = array_filter([
            'before_due' => $settings['days_before'] > 0 ? today()->addDays($settings['days_before']) : null,
            'due_today' => $settings['on_due_date'] ? today() : null,
        ]);
        foreach ($targets as $kind => $date) {
            $installments = FeeInstallment::outstanding()->whereDate('due_date', $date->toDateString())
                ->whereHas('enrollment', fn ($query) => $query->whereIn('status', ['active', 'completed'])
                    ->whereHas('student', fn ($student) => $student->where('is_active', true)))
                ->with(['enrollment.student', 'enrollment.batch.course'])->orderBy('id')->get();
            foreach ($installments as $installment) {
                $message = $this->deliver($installment, $kind, "{$kind}:{$installment->id}", null);
                $totals[$message ? ($message->status === 'sent' ? 'sent' : 'failed') : 'skipped']++;
            }
        }

        return $totals;
    }

    /** Admin button: remind about the earliest unpaid installment of one enrollment. */
    public function sendManual(Enrollment $enrollment, User $actor): WhatsappMessage
    {
        abort_unless($actor->isAdmin() && $actor->is_active, 403);
        $installment = FeeInstallment::outstanding()->where('enrollment_id', $enrollment->id)->orderBy('due_date')->orderBy('id')
            ->with(['enrollment.student', 'enrollment.batch.course'])->first();
        if (! $installment || $enrollment->status === 'transferred') {
            throw ValidationException::withMessages(['whatsapp' => 'This enrollment has no unpaid installment.']);
        }
        if (WhatsappMessage::where('fee_installment_id', $installment->id)->where('kind', 'manual')->where('status', 'sent')
            ->where('created_at', '>', now()->subDay())->exists()) {
            throw ValidationException::withMessages(['whatsapp' => 'A reminder for this installment was already sent in the last 24 hours.']);
        }

        return $this->deliver($installment, 'manual', null, $actor);
    }

    /** Admin check of the setup: sends the template with sample values to any number. */
    public function sendTest(string $phone, User $actor): WhatsappMessage
    {
        abort_unless($actor->isAdmin() && $actor->is_active, 403);
        $params = [$actor->name, currency_format('5000'), today()->format('d M Y'), 'German A1 · Sample batch', institute_name()];

        return $this->send(null, null, null, 'test', null, $phone, $params, $actor);
    }

    /** Null when an automatic reminder was already recorded (another run got there first). */
    private function deliver(FeeInstallment $installment, string $kind, ?string $key, ?User $actor): ?WhatsappMessage
    {
        $enrollment = $installment->enrollment;
        $student = $enrollment->student;
        $batch = $enrollment->batch;
        $params = [$student->name, currency_format($installment->outstanding_amount), $installment->due_date->format('d M Y'),
            trim(($batch?->course?->name ?? 'Course').' · '.($batch?->name ?? '')), institute_name()];

        return $this->send($student->id, $enrollment->id, $installment->id, $kind, $key, $student->phone, $params, $actor);
    }

    private function send(?int $studentId, ?int $enrollmentId, ?int $installmentId, string $kind, ?string $key,
        ?string $phone, array $params, ?User $actor): ?WhatsappMessage
    {
        $settings = $this->settings();
        $number = $this->whatsApp->normalizePhone($phone);
        try {
            // Claim first: the unique key stops a second run from messaging the same student twice.
            $message = WhatsappMessage::create(['student_id' => $studentId, 'enrollment_id' => $enrollmentId,
                'fee_installment_id' => $installmentId, 'kind' => $kind, 'dedupe_key' => $key, 'phone' => $number ?? mb_substr((string) $phone, 0, 20),
                'template' => $settings['template'], 'params' => $params, 'status' => 'sending', 'sent_by' => $actor?->id]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
        if ($number === null) {
            $message->update(['status' => 'failed', 'error' => 'Missing or invalid phone number on the student profile.']);

            return $message;
        }
        try {
            $id = $this->whatsApp->sendTemplate($number, $settings['template'], $settings['language'], $params);
            $message->update(['status' => 'sent', 'provider_message_id' => $id]);
        } catch (\RuntimeException $exception) {
            $message->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 1000)]);
        }

        return $message;
    }
}
