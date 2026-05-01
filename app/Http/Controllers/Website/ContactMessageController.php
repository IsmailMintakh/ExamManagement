<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ContactMessage::query();

        $tab = $request->string('tab')->value() ?: 'inbox';
        if ($tab === 'unread')   $query->unread();
        elseif ($tab === 'archived') $query->where('is_archived', true);
        else                     $query->inbox();

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(fn ($q) => $q->where('name', 'like', "%$term%")
                ->orWhere('email', 'like', "%$term%")
                ->orWhere('subject', 'like', "%$term%")
                ->orWhere('message', 'like', "%$term%"));
        }

        return Inertia::render('Website/ContactMessages/Index', [
            'messages' => $query->orderByDesc('created_at')->paginate(25)->withQueryString(),
            'filters'  => $request->only(['search', 'tab']),
            'tab'      => $tab,
            'counts'   => [
                'inbox'    => ContactMessage::inbox()->count(),
                'unread'   => ContactMessage::unread()->count(),
                'archived' => ContactMessage::where('is_archived', true)->count(),
            ],
        ]);
    }

    public function show(ContactMessage $message): Response
    {
        // Auto-mark as read on first view
        if (!$message->is_read) $message->update(['is_read' => true]);

        return Inertia::render('Website/ContactMessages/Show', [
            'message' => $message,
        ]);
    }

    public function update(Request $request, ContactMessage $message): RedirectResponse
    {
        $data = $request->validate([
            'is_read'     => ['nullable', 'boolean'],
            'is_archived' => ['nullable', 'boolean'],
            'replied_at'  => ['nullable', 'date'],
        ]);

        $message->update(array_filter($data, fn ($v) => $v !== null));

        return back()->with('success', 'Message updated.');
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();
        return redirect()->route('website.contact-messages.index')->with('success', 'Message deleted.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:read,unread,archive,unarchive,delete'],
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
        ]);

        $query = ContactMessage::whereIn('id', $data['ids']);

        match ($data['action']) {
            'read'      => $query->update(['is_read' => true]),
            'unread'    => $query->update(['is_read' => false]),
            'archive'   => $query->update(['is_archived' => true]),
            'unarchive' => $query->update(['is_archived' => false]),
            'delete'    => $query->delete(),
        };

        return back()->with('success', count($data['ids']) . ' message(s) updated.');
    }
}
