<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    /** JSON feed for the bell dropdown (latest 15 + unread count). */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = $user->notifications()->latest()->limit(15)->get()->map(fn ($n) => [
            'id' => $n->id,
            'read' => $n->read_at !== null,
            'title' => $n->data['title'] ?? 'Notification',
            'body' => $n->data['body'] ?? '',
            'url' => $n->data['url'] ?? null,
            'icon' => $n->data['icon'] ?? 'fa-bell',
            'level' => $n->data['level'] ?? 'info',
            'time' => $n->created_at->diffForHumans(),
        ]);

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $items,
        ]);
    }

    /** Full history page. */
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(25);

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();
        $notification?->markAsRead();

        return response()->json(['ok' => true, 'unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true, 'unread' => 0]);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereKey($id)->delete();

        return back()->with('success', 'Notification removed.');
    }

    /** Admin announcement composer. */
    public function createAnnouncement(): View
    {
        return view('notifications.announce');
    }

    /** Admin broadcast to teachers and/or students. */
    public function sendAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
            'audience' => ['required', 'array', 'min:1'],
            'audience.*' => ['in:teacher,student'],
        ]);

        $this->notifications->broadcastAll([
            'type' => 'announcement',
            'title' => $data['title'],
            'body' => $data['body'],
            'icon' => 'fa-bullhorn',
            'level' => 'info',
            'url' => route('notifications.index'),
        ], $data['audience'], auth()->id());

        return back()->with('success', 'Announcement sent to '.implode(' and ', $data['audience']).'.');
    }
}
