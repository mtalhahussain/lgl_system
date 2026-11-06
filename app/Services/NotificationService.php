<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Central fan-out for in-app + Pusher notifications.
 *
 * Every domain action routes through here so delivery is consistent and a
 * notification failure (e.g. unconfigured Pusher keys) can never break the
 * business transaction that triggered it — all sends are guarded.
 */
class NotificationService
{
    /** Send to one user (model or id). Silently skips a missing/inactive target. */
    public function toUser(User|int|null $user, array $payload): void
    {
        $user = $user instanceof User ? $user : ($user ? User::find($user) : null);

        if (! $user) {
            return;
        }

        $this->dispatch(collect([$user]), $payload);
    }

    /** Send to an arbitrary collection/array of users. */
    public function toUsers(iterable $users, array $payload): void
    {
        $this->dispatch(collect($users)->filter(), $payload);
    }

    /** Send to every active Admin (excluding $exceptId, usually the actor). */
    public function toAdmins(array $payload, ?int $exceptId = null): void
    {
        $admins = User::where('role', 'admin')
            ->where('is_active', true)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->get();

        $this->dispatch($admins, $payload);
    }

    /** Send to every active user holding a given role. */
    public function toRole(string $role, array $payload, ?int $exceptId = null): void
    {
        $users = User::where('role', $role)
            ->where('is_active', true)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->get();

        $this->dispatch($users, $payload);
    }

    /** Send to all students with an active enrollment in a batch. */
    public function toBatchStudents(Batch|int $batch, array $payload): void
    {
        $batchId = $batch instanceof Batch ? $batch->id : $batch;

        $students = User::where('role', 'student')
            ->where('is_active', true)
            ->whereIn('id', Enrollment::where('batch_id', $batchId)
                ->where('status', 'active')
                ->pluck('student_id'))
            ->get();

        $this->dispatch($students, $payload);
    }

    /** Admin announcement: fan out to all active teachers and students. */
    public function broadcastAll(array $payload, array $roles = ['teacher', 'student'], ?int $exceptId = null): void
    {
        $users = User::whereIn('role', $roles)
            ->where('is_active', true)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->get();

        $this->dispatch($users, $payload);
    }

    /** Guarded delivery. Never throws into the caller's transaction. */
    private function dispatch(Collection $recipients, array $payload): void
    {
        $recipients = $recipients->filter()->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, new SystemNotification($payload));
        } catch (\Throwable $e) {
            Log::warning('Notification dispatch failed', [
                'type' => $payload['type'] ?? 'system',
                'recipients' => $recipients->pluck('id')->all(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
