<?php

namespace App\Http\Middleware;

use App\Models\AcademicSession;
use App\Models\ContactMessage;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar_url,
                    'school_id' => $user->school_id,
                    'school' => $user->school ? [
                        'id' => $user->school->id,
                        'name' => $user->school->name,
                        'code' => $user->school->code,
                        'logo_url' => $user->school->logo_url,
                    ] : null,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ] : null,
                'currentSession' => AcademicSession::currentSession(),
                'sessions' => AcademicSession::active()->orderBy('start_date', 'desc')->get(['id', 'name', 'is_current']),
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],

            'notifications' => fn () => $user
                ? $user->unreadNotifications()->latest()->take(5)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'type' => class_basename($n->type),
                    'data' => $n->data,
                    'created_at' => $n->created_at->diffForHumans(),
                ])
                : [],

            'notificationCount' => fn () => $user ? $user->unreadNotifications()->count() : 0,

            // Unread public-website contact messages — only DDOs see this
            'contactMessageCount' => fn () => $user?->can('website.manage')
                ? ContactMessage::unread()->count()
                : 0,

            // Site settings shared globally so PublicLayout (header/footer)
            // and any page can reference them without per-controller injection.
            'site' => fn () => SiteSetting::allCached(),
        ];
    }
}
