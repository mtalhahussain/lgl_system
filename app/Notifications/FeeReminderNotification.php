<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeeReminderNotification extends Notification
{
    use Queueable;

    private $installment;
    private $enrollment;

    public function __construct($installment, $enrollment)
    {
        $this->installment = $installment;
        $this->enrollment = $enrollment;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $courseName = $this->enrollment->batch->course->name . ' - ' . $this->enrollment->batch->course->level;
        $amount = number_format($this->installment->amount, 0);
        $dueDate = $this->installment->due_date->format('d.m.Y');

        return (new MailMessage)
                    ->subject('Fee Payment Reminder - ' . $courseName)
                    ->greeting('Dear ' . $notifiable->name . ',')
                    ->line('This is a friendly reminder about your upcoming fee payment.')
                    ->line('Course: ' . $courseName)
                    ->line('Amount Due: ' . currency_format($amount))
                    ->line('Due Date: ' . $dueDate)
                    ->line('Please make your payment to avoid any inconvenience.')
                    ->action('Pay Now', url('/payments/' . $this->installment->id))
                    ->line('If you have already made the payment, please ignore this reminder.')
                    ->line('For any questions, please contact our accounts department.')
                    ->salutation('Best regards, German Language Institute');
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'fee_reminder',
            'installment_id' => $this->installment->id,
            'enrollment_id' => $this->enrollment->id,
            'amount' => $this->installment->amount,
            'due_date' => $this->installment->due_date,
            'course_name' => $this->enrollment->batch->course->name . ' - ' . $this->enrollment->batch->course->level,
        ];
    }
}