<?php

namespace Tests\Feature;

use App\Actions\Identity\DeliverIdentityMessage;
use App\Models\ContractorDocument;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use Illuminate\Support\Facades\Queue;
use Tests\Support\IdentityTestCase;

class StagedInsuranceReminderTest extends IdentityTestCase
{
    public function test_each_stage_is_deduplicated_and_expired_or_changed_documents_are_rechecked(): void
    {
        config(['notifications.business_enabled' => true]);
        Queue::fake();
        $start = today()->toImmutable();
        $this->travelTo($start);
        ContractorDocument::query()->update(['archived' => 1]);
        $document = (new ContractorDocument)->forceFill([
            'user_id' => 2, 'unique_file_id' => str_repeat('s', 32), 'file_type' => 'Insurance',
            'file_name' => 'staged.pdf', 'file_data' => "%PDF-1.4\n%%EOF", 'mime_type' => 'application/pdf',
            'upload_date' => now(), 'expiry_date' => $start->addDays(30), 'archived' => 0,
        ]);
        $document->save();
        // The 30-day staged reminder shares the original reminder's identity.
        $this->artisan('nestcrafters:notify-document-expiry')->assertSuccessful();
        foreach ([0, 23, 30, 31] as $days) {
            $this->travelTo($start->addDays($days));
            $this->artisan('nestcrafters:notify-document-expiry', ['--staged' => true])->assertSuccessful();
            $this->artisan('nestcrafters:notify-document-expiry', ['--staged' => true])->assertSuccessful();
        }
        $this->assertSame(4, NotificationEvent::where('type', 'document.expiring')->count());
        $expired = NotificationEvent::get()->first(fn ($event) => ($event->payload['stage'] ?? null) === -1);
        $delivery = $expired->deliveries()->firstOrFail();
        app(DeliverIdentityMessage::class)->run($delivery->id);
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertSame(1, NotificationDelivery::where('status', 'delivered')->count());
        $document->forceFill(['expiry_date' => $start->addDays(40)])->save();
        foreach (NotificationDelivery::where('status', 'pending')->get() as $pending) {
            app(DeliverIdentityMessage::class)->run($pending->id);
            $this->assertSame('skipped', $pending->fresh()->status);
        }
        $this->travelBack();
    }
}
