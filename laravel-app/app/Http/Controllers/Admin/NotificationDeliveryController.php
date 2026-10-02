<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Identity\SecurityAudit;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverNotificationDelivery;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class NotificationDeliveryController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate(['status' => ['nullable', 'string', 'in:pending,failed,delivered,skipped']]);

        return response()->view('notifications.deliveries', [
            'deliveries' => NotificationDelivery::with('event')->where('status', $filters['status'] ?? 'failed')->orderByDesc('id')->paginate(20)->withQueryString(),
            'status' => $filters['status'] ?? 'failed',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function retry(Request $request, string $delivery)
    {
        Gate::authorize('viewAny', User::class);
        $request->validate(['confirmed' => ['required', 'accepted']]);
        DB::transaction(function () use ($delivery, $request): void {
            $record = NotificationDelivery::lockForUpdate()->findOrFail($delivery);
            if ($record->status !== 'failed') {
                throw ValidationException::withMessages(['delivery' => 'Only failed deliveries can be retried here. Refresh the list.']);
            }
            $record->forceFill(['status' => 'pending', 'available_at' => now()])->save();
            app(SecurityAudit::class)->record($request->user(), 'requested retry for notification delivery '.$record->id.'.');
            DB::afterCommit(function () use ($record): void {
                try {
                    DeliverNotificationDelivery::dispatch($record->id);
                } catch (Throwable $exception) {
                    Log::warning('Notification retry remains pending.', ['delivery_id' => $record->id, 'exception_class' => $exception::class]);
                }
            });
        });

        return back()->with('status', 'Retry requested. Current permissions and email preferences will be checked again.');
    }
}
