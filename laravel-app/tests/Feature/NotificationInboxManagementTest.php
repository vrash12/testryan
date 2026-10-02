<?php

namespace Tests\Feature;

use App\Models\InboxNotification;
use App\Models\NotificationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\IdentityTestCase;

class NotificationInboxManagementTest extends IdentityTestCase
{
    private function item(int $user, string $type = 'contractor.approval_changed', string $at = '2026-10-02 12:00:00', ?string $readAt = null): InboxNotification
    {
        $event = NotificationEvent::unguarded(fn () => NotificationEvent::create([
            'type' => $type, 'subject_user_id' => $user,
            'deduplication_key' => hash('sha256', (string) Str::ulid()),
            'payload' => ['private_test_marker' => 'NEVER-DISPLAY-PAYLOAD'], 'created_at' => $at,
        ]));

        return InboxNotification::unguarded(fn () => InboxNotification::create([
            'event_id' => $event->id, 'user_id' => $user, 'created_at' => $at, 'read_at' => $readAt,
        ]));
    }

    public function test_filters_are_owned_inclusive_and_counts_remain_inbox_wide(): void
    {
        $start = $this->item(2, at: '2026-10-02 00:00:00');
        $end = $this->item(2, at: '2026-10-02 23:59:59');
        $read = $this->item(2, at: '2026-10-02 16:00:00', readAt: '2026-10-02 18:00:00');
        $this->item(2, 'job.updated');
        $this->item(2, at: '2026-10-01 23:59:59');
        $this->item(2, at: '2026-10-03 00:00:00');
        $this->item(3);
        $this->signInAs(2);
        $this->get(route('notifications.index', ['status' => 'unread', 'type' => 'contractor.approval_changed', 'from' => '2026-10-02', 'to' => '2026-10-02']))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertViewHas('total', 6)->assertViewHas('unread', 5)
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->all() === [$end->id, $start->id])
            ->assertDontSee('NEVER-DISPLAY-PAYLOAD');
        $this->get(route('notifications.index', ['status' => 'read']))->assertOk()
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->all() === [$read->id]);
        $this->get(route('notifications.index', ['unread' => 1]))->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 5);
        $this->get(route('notifications.index', ['type' => 'job.completed']))->assertOk()->assertSee('No updates match these filters');
    }

    public function test_selected_changes_are_owned_idempotent_reversible_and_do_not_create_mail(): void
    {
        Queue::fake();
        $first = $this->item(2);
        $second = $this->item(2);
        $untouched = $this->item(2);
        $other = $this->item(3);
        $this->signInAs(2);
        $before = [NotificationEvent::count(), DB::table('app_notification_deliveries')->count()];
        $payload = ['ids' => [$first->id, $second->id], 'action' => 'read', 'status' => 'unread', 'type' => 'contractor.approval_changed', 'page' => 4, 'user_id' => 3];
        $this->post(route('notifications.bulk'), $payload)->assertRedirect(route('notifications.index', ['status' => 'unread', 'type' => 'contractor.approval_changed']))->assertSessionHas('status', '2 notification(s) marked read.');
        $at = $first->fresh()->read_at->toDateTimeString();
        $this->assertSame('2026-10-02 12:00:00', $first->fresh()->created_at->toDateTimeString());
        $this->travel(1)->minutes();
        $this->post(route('notifications.bulk'), $payload)->assertRedirect()->assertSessionHas('status', '0 notification(s) marked read.');
        $this->assertSame($at, $first->fresh()->read_at->toDateTimeString());
        $this->assertNull($untouched->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
        $this->post(route('notifications.bulk'), ['ids' => [$first->id], 'action' => 'unread'])->assertRedirect()->assertSessionHas('status', '1 notification(s) marked unread.');
        $this->assertNull($first->fresh()->read_at);
        $this->assertSame('2026-10-02 12:00:00', $first->fresh()->created_at->toDateTimeString());
        $this->assertNotNull($second->fresh()->read_at);
        $this->post(route('notifications.read', $first))->assertRedirect();
        $this->assertSame('2026-10-02 12:00:00', $first->fresh()->created_at->toDateTimeString());
        $this->assertSame($before, [NotificationEvent::count(), DB::table('app_notification_deliveries')->count()]);
        Queue::assertNothingPushed();
    }

    public function test_mixed_missing_or_foreign_selections_do_not_change_any_owned_row_even_for_admin(): void
    {
        $own = $this->item(2);
        $foreign = $this->item(3);
        $this->signInAs(2);
        foreach ([$foreign->id, (string) Str::ulid()] as $id) {
            $this->postJson(route('notifications.bulk'), ['ids' => [$own->id, $id], 'action' => 'read'])->assertNotFound();
            $this->assertNull($own->fresh()->read_at);
        }
        $admin = $this->item(1, 'registration.created');
        $this->signInAs(1);
        $this->postJson(route('notifications.bulk'), ['ids' => [$admin->id, $own->id], 'action' => 'read'])->assertNotFound();
        $this->assertNull($admin->fresh()->read_at);
        $this->post(route('notifications.bulk'), ['ids' => [$admin->id], 'action' => 'read'])->assertRedirect();
        $this->get(route('notifications.index', ['status' => 'read']))->assertOk()->assertSee('Mark unread');
    }

    public function test_invalid_filters_selections_and_account_states_fail_before_mutation(): void
    {
        $item = $this->item(2);
        $this->signInAs(2);
        foreach ([['status' => 'invalid'], ['type' => 'password.reset'], ['from' => '2026-02-30'], ['from' => '2026-10-02', 'to' => '2026-10-01'], ['page' => 0]] as $filter) {
            $this->getJson(route('notifications.index', $filter))->assertUnprocessable();
        }
        foreach ([[], ['ids' => []], ['ids' => [$item->id, $item->id]], ['ids' => ['bad-id']], ['ids' => array_map(fn () => (string) Str::ulid(), range(1, 51))], ['ids' => [$item->id], 'action' => 'delete']] as $invalid) {
            $this->postJson(route('notifications.bulk'), array_replace(['action' => 'read'], $invalid))->assertUnprocessable();
        }
        $this->assertNull($item->fresh()->read_at);
        User::whereKey(2)->update(['is_approved' => 2]);
        $this->signInAs(2);
        $this->postJson(route('notifications.bulk'), ['ids' => [$item->id], 'action' => 'read'])->assertForbidden();
        User::whereKey(2)->update(['is_approved' => 1]);
        $this->unverify(2);
        $this->signInAs(2);
        $this->post(route('notifications.bulk'), ['ids' => [$item->id], 'action' => 'read'])->assertRedirect(route('verification.notice'));
        $this->assertNull($item->fresh()->read_at);
    }

    public function test_pagination_preserves_filters_and_bulk_form_only_lists_current_page(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->item(2);
        }
        $this->signInAs(2);
        $this->get(route('notifications.index', ['type' => 'contractor.approval_changed', 'status' => 'unread', 'page' => 2]))->assertOk()
            ->assertViewHas('items', fn ($items) => $items->count() === 1 && $items->total() === 21 && str_contains($items->url(1), 'status=unread'))
            ->assertSee('Select this page')->assertSee('Apply to selected')->assertSee('form="notification-bulk"', false);
    }
}
