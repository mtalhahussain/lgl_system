<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnrollmentConfirmationNotification extends Notification
{
    use Queueable;

    private $enrollment;

    public function __construct($enrollment)
    {
        $this->enrollment = $enrollment;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $courseName = $this->enrollment->batch->course->name . ' - ' . $this->enrollment->batch->course->level;
        $batchName = $this->enrollment->batch->name;
        $startDate = $this->enrollment->batch->start_date->format('d.m.Y');

        return (new MailMessage)
                    ->subject('Welcome to ' . $courseName)
                    ->greeting('Dear ' . $notifiable->name . ',')
                    ->line('Congratulations! You have been successfully enrolled in our German language course.')
                    ->line('Course: ' . $courseName)
                    ->line('Batch: ' . $batchName)
                    ->line('Start Date: ' . $startDate)
                    ->line('Teacher: ' . $this->enrollment->batch->teacher->name)
                    ->line('Total Fee: ' . currency_format($this->enrollment->total_fee))
                    ->line('Your learning journey begins soon. We are excited to help you master the German language!')
                    ->action('View Course Details', url('/dashboard'))
                    ->line('For any questions, please contact us.')
                    ->salutation('Willkommen! German Language Institute');
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'enrollment_confirmation',
            'enrollment_id' => $this->enrollment->id,
            'batch_id' => $this->enrollment->batch_id,
            'course_name' => $this->enrollment->batch->course->name . ' - ' . $this->enrollment->batch->course->level,
            'batch_name' => $this->enrollment->batch->name,
            'start_date' => $this->enrollment->batch->start_date,
        ];
    }
}