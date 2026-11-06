<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic in-app + real-time notification.
 *
 * One reusable class fans every domain action (enrollment, payment, exam,
 * certificate, attendance, leave, payout, admin broadcast) out to the
 * `database` store and the `broadcast` (Pusher) channel with a consistent
 * payload the notification bell can render directly.
 */
class SystemNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{type:string,title:string,body?:string,url?:string,icon?:string,level?:string,meta?:array}  $payload
     */
    public function __construct(public array $payload)
    {
    }

    /**
     * Delivery channels. Mail is intentionally omitted here; it stays on the
     * purpose-built mail notifications. Broadcast only runs when a real-time
     * driver is configured, otherwise it is a harmless no-op.
     */
    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => $this->payload['type'] ?? 'system',
            'title' => $this->payload['title'] ?? 'Notification',
            'body' => $this->payload['body'] ?? '',
            'url' => $this->payload['url'] ?? null,
            'icon' => $this->payload['icon'] ?? 'fa-bell',
            'level' => $this->payload['level'] ?? 'info', // info|success|warning|danger
            'meta' => $this->payload['meta'] ?? [],
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))
            ->onQueue('broadcasts');
    }

    /**
     * Keep the client-side event name stable: Echo listens with `.notification`.
     */
    public function broadcastType(): string
    {
        return 'notification';
    }
}
