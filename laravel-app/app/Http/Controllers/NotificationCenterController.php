<?php

namespace App\Http\Controllers;

use App\Actions\Identity\SecurityAudit;
use App\Actions\Notifications\NotificationInbox;
use App\Actions\Notifications\NotificationPreferences;
use App\Http\Requests\NotificationInboxRequest;
use App\Models\InboxNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationCenterController extends Controller
{
    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()->role() === 'admin' || ($request->user()->role() === 'contractor' && $request->user()->is_approved === 1), 403);
    }

    public function index(NotificationInboxRequest $request, NotificationInbox $inbox)
    {
        $this->authorizeAccess($request);
        $filters = $request->filters();
        $owned = InboxNotification::where('user_id', $request->user()->user_id);
        $total = (clone $owned)->count();
        $unread = (clone $owned)->whereNull('read_at')->count();
        $items = $owned->with('event')
            ->when($filters['status'] === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($filters['status'] === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->whereHas('event', fn ($event) => $event->where('type', $type)))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->appends($filters);

        return response()->view('notifications.index', [
            'items' => $items, 'inbox' => $inbox, 'labels' => NotificationPreferences::LABELS,
            'filters' => $filters, 'total' => $total, 'unread' => $unread,
            'timezone' => config('app.timezone'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function bulk(NotificationInboxRequest $request)
    {
        $data = $request->validated();
        $changed = DB::transaction(function () use ($request, $data): int {
            $query = InboxNotification::where('user_id', $request->user()->user_id)->whereIn('id', $data['ids']);
            $items = (clone $query)->orderBy('id')->lockForUpdate()->get();
            // Reject the entire selection if any row is missing or belongs to someone else.
            abort_unless($items->count() === count($data['ids']), 404);

            return $data['action'] === 'read'
                ? $query->whereNull('read_at')->update(['read_at' => now()])
                : $query->whereNotNull('read_at')->update(['read_at' => null]);
        });

        // Return to the first filtered page: changing read status may empty later pages.
        return redirect()->route('notifications.index', $request->filters())
            ->with('status', $changed.' notification(s) marked '.$data['action'].'.');
    }

    public function read(Request $request, string $notification)
    {
        $this->authorizeAccess($request);
        $item = InboxNotification::where('user_id', $request->user()->user_id)->findOrFail($notification);
        InboxNotification::whereKey($item->id)->where('user_id', $request->user()->user_id)
            ->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'Notification marked as read.');
    }

    public function preferences(Request $request, NotificationPreferences $preferences)
    {
        $this->authorizeAccess($request);

        return response()->view('notifications.preferences', [
            'choices' => $preferences->choices($request->user()),
            'enabled' => array_keys(array_filter($preferences->choices($request->user()), fn ($label, $type) => $preferences->emailEnabled($request->user(), $type), ARRAY_FILTER_USE_BOTH)),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function updatePreferences(Request $request, NotificationPreferences $preferences)
    {
        $this->authorizeAccess($request);
        $choices = $preferences->choices($request->user());
        $data = $request->validate(['email_events' => ['sometimes', 'array', 'max:10'], 'email_events.*' => ['required', 'string', 'distinct', Rule::in(array_keys($choices))]]);
        DB::transaction(function () use ($request, $choices, $data): void {
            foreach ($choices as $type => $label) {
                DB::table('app_notification_preferences')->updateOrInsert(
                    ['user_id' => $request->user()->user_id, 'event_type' => $type],
                    ['email_enabled' => in_array($type, $data['email_events'] ?? [], true)]
                );
            }
            app(SecurityAudit::class)->record($request->user(), 'updated own notification email preferences.');
        });

        return redirect()->route('notifications.preferences')->with('status', 'Email preferences saved.');
    }
}
