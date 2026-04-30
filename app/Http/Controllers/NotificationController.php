<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->when($request->has('type'), function ($query) use ($request) {
                $query->where('type', $request->input('type'));
            })
            ->when($request->has('read'), function ($query) use ($request) {
                if ($request->input('read') === 'unread') {
                    $query->whereNull('read_at');
                } elseif ($request->input('read') === 'read') {
                    $query->whereNotNull('read_at');
                }
            })
            ->paginate(15)
            ->withQueryString();

        $unreadCount = $user->unreadNotifications()->count();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'filters' => $request->only(['type', 'read']),
        ]);
    }

    /**
     * AJAX endpoint for the notification drawer — returns the latest 15 grouped by date bucket.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $latest = $user->notifications()->latest()->limit(15)->get();
        $unreadCount = $user->unreadNotifications()->count();

        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $weekAgo = now()->subDays(7)->startOfDay();

        $groups = [
            'today' => [],
            'yesterday' => [],
            'this_week' => [],
            'older' => [],
        ];

        foreach ($latest as $n) {
            $created = $n->created_at;
            $entry = [
                'id' => $n->id,
                'type' => $this->shortType($n->type),
                'title' => $n->data['title'] ?? 'Notification',
                'message' => $n->data['message'] ?? '',
                'action_url' => $n->data['action_url'] ?? null,
                'action_label' => $n->data['action_label'] ?? null,
                'severity' => $n->data['severity'] ?? 'info',
                'read' => $n->read_at !== null,
                'created_at' => $created->toIso8601String(),
                'time_ago' => $created->diffForHumans(),
            ];

            if ($created >= $today) $groups['today'][] = $entry;
            elseif ($created >= $yesterday) $groups['yesterday'][] = $entry;
            elseif ($created >= $weekAgo) $groups['this_week'][] = $entry;
            else $groups['older'][] = $entry;
        }

        return response()->json([
            'groups' => $groups,
            'unread_count' => $unreadCount,
            'total' => $latest->count(),
        ]);
    }

    public function markAsRead(string $id): RedirectResponse|JsonResponse
    {
        $user = request()->user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        if (request()->expectsJson()) {
            return response()->json(['ok' => true, 'unread_count' => $user->unreadNotifications()->count()]);
        }
        return redirect()->back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'unread_count' => 0]);
        }
        return redirect()->back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Strip the FQCN class prefix off Laravel notification types so the UI sees a clean key.
     */
    protected function shortType(?string $type): string
    {
        if (!$type) return 'general';
        $base = class_basename($type);
        // Marks → "marks", MarksSubmittedNotification → "marks_submitted"
        return strtolower(preg_replace('/Notification$/', '', $base) ?: 'general');
    }
}
