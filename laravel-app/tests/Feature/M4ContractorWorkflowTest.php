<?php

namespace Tests\Feature;

use App\Models\ContractorDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IdentityTestCase;

class M4ContractorWorkflowTest extends IdentityTestCase
{
    public function test_admin_can_search_create_update_approve_and_archive_contractors(): void
    {
        Storage::fake('private');
        $this->signInAs(1);
        $this->get(route('admin.contractors.index', ['q' => 'contractor']))
            ->assertOk()->assertSee('Contractor');

        $payload = [
            'first_name' => 'Created', 'last_name' => 'Contractor', 'company_name' => 'Created Co',
            'company_address' => 'Created address', 'company_phone' => '+1 555 010 9090',
            'phone1' => '+1 555 010 9091', 'phone1_type' => 'Mobile', 'phone2' => '+1 555 010 9092', 'phone2_type' => 'Office',
            'email' => 'created@example.test', 'services' => [3], 'priority' => '1',
            'profile_photo' => UploadedFile::fake()->image('created.png'),
        ];
        $response = $this->post(route('admin.contractors.store'), $payload);
        $created = User::where('email', 'created@example.test')->firstOrFail();
        $response->assertRedirect(route('admin.contractors.show', $created));
        $this->assertSame(0, $created->is_approved);
        $this->assertTrue($created->services()->whereKey(3)->exists());
        $this->assertNotNull($created->profileUpload);
        $this->assertDatabaseHas('app_account_states', ['user_id' => $created->user_id, 'email_verified_at' => null]);
        $this->assertDatabaseHas('ActivityLogs', ['user_id' => 1]);

        $this->post(route('admin.contractors.approval', $created), ['approval' => 1, 'reason' => 'Reviewed'])->assertRedirect();
        $created->refresh();
        $this->assertSame(1, $created->is_approved);

        $this->put(route('admin.contractors.update', $created), array_merge($payload, [
            'first_name' => 'Updated', 'email' => 'created@example.test', 'services' => [3], 'priority' => '0',
        ]))->assertRedirect();
        $created->refresh();
        $this->assertSame(2, $created->is_approved);
        $this->assertSame(0, $created->priority);
        $this->assertNotNull($created->accountState->session_version);

        $this->post(route('admin.contractors.archive', $created))->assertRedirect(route('admin.contractors.index'));
        $this->assertNotNull($created->fresh()->accountState->archived_at);
        $this->post(route('admin.contractors.restore', $created))->assertRedirect();
        $this->assertNull($created->fresh()->accountState->archived_at);
        $this->delete(route('admin.contractors.destroy', $created))->assertRedirect(route('admin.contractors.index'));
        $this->assertDatabaseMissing('Users', ['user_id' => $created->user_id]);
        $this->assertDatabaseHas('ActivityLogs', ['user_id' => 1, 'action' => 'Security: permanently deleted contractor account #'.$created->user_id.'.']);
    }

    public function test_contractor_profile_changes_return_account_to_review_and_cross_owner_is_denied(): void
    {
        $contractor = $this->signInAs(2);
        $payload = [
            'first_name' => $contractor->first_name, 'last_name' => $contractor->last_name,
            'company_name' => 'Changed by contractor', 'company_address' => $contractor->company_address,
            'company_phone' => $contractor->company_phone, 'phone1' => $contractor->phone1,
            'phone1_type' => $contractor->phone1_type, 'phone2' => '+1 555 010 9999',
            'phone2_type' => $contractor->phone2_type, 'email' => $contractor->email,
        ];
        $response = $this->put(route('contractor.profile.update', $contractor), $payload);
        $response->assertRedirect(route('account.status'))->assertSessionHas('status');
        $this->assertSame(2, $contractor->fresh()->is_approved);
        $this->assertFalse($contractor->fresh()->services()->exists());
        $this->get(route('contractor.profile.edit', 3))->assertForbidden();
        $this->get(route('contractor.documents.index', 3))->assertForbidden();
    }

    public function test_admin_notes_and_service_catalog_have_safe_usage_guards(): void
    {
        $this->signInAs(1);
        $this->post(route('admin.contractors.notes.store', 2), ['note_text' => 'Follow up insurance.'])->assertRedirect();
        $this->assertDatabaseHas('Notes', ['user_id' => 2, 'admin_id' => 1, 'note_text' => 'Follow up insurance.']);

        $this->post(route('admin.services.store'), ['service_name' => 'New fixture service'])->assertRedirect();
        $service = DB::table('Services')->where('service_name', 'New fixture service')->first();
        $this->assertNotNull($service);
        $this->put(route('admin.services.update', $service->service_id), ['service_name' => 'Renamed fixture service'])->assertRedirect();
        $this->delete(route('admin.services.destroy', 3))->assertSessionHasErrors('service');
        $this->delete(route('admin.services.destroy', $service->service_id))->assertRedirect(route('admin.services.index'));
        $this->delete(route('admin.contractors.destroy', 2))->assertSessionHasErrors('contractor');
        $this->assertDatabaseHas('Users', ['user_id' => 2]);

        $this->signInAs(2);
        $this->get(route('admin.services.index'))->assertForbidden();
        $this->post(route('admin.contractors.notes.store', 2), ['note_text' => 'Nope'])->assertForbidden();
    }

    public function test_private_document_upload_download_ownership_and_archival(): void
    {
        Storage::fake('private');
        $this->signInAs(2);
        $file = UploadedFile::fake()->create('w9.pdf', 12, 'application/pdf');
        $this->post(route('contractor.documents.store', 2), [
            'document' => $file, 'file_type' => 'W9', 'file_name' => 'w9-2026.pdf', 'expiry_date' => now()->addMonths(8)->toDateString(),
        ])->assertRedirect();
        $document = ContractorDocument::where('user_id', 2)->latest('file_id')->firstOrFail();
        $upload = $document->privateUpload;
        $this->assertNotNull($upload);
        Storage::disk('private')->assertExists($upload->path);
        $this->get(route('documents.download', $document))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="w9-2026.pdf"');

        $this->signInAs(3);
        $this->get(route('documents.download', $document))->assertForbidden();
        $this->signInAs(2);
        $expired = UploadedFile::fake()->create('expired.pdf', 2, 'application/pdf');
        $this->post(route('contractor.documents.store', 2), [
            'document' => $expired, 'file_type' => 'Insurance', 'file_name' => 'expired.pdf', 'expiry_date' => now()->subDay()->toDateString(),
        ])->assertRedirect();
        $this->post(route('contractor.documents.store', 2), [
            'document' => UploadedFile::fake()->create('bad.txt', 2, 'text/plain'), 'file_type' => 'Other',
        ])->assertSessionHasErrors('document');
        $this->signInAs(1);
        $this->get(route('documents.preview', $document))->assertOk()->assertHeader('Content-Disposition', 'inline; filename="w9-2026.pdf"');
        $this->post(route('admin.contractors.documents.archive', [2, $document]))->assertRedirect();
        $this->assertSame(1, $document->fresh()->archived);
        $this->delete(route('admin.contractors.documents.destroy', [2, $document]))->assertRedirect();
        $this->assertDatabaseMissing('Files', ['file_id' => $document->file_id]);

        $contractor = User::findOrFail(2);
        $this->put(route('admin.contractors.update', $contractor), [
            'first_name' => $contractor->first_name, 'last_name' => $contractor->last_name,
            'company_name' => $contractor->company_name, 'company_address' => $contractor->company_address,
            'company_phone' => $contractor->company_phone, 'phone1' => $contractor->phone1,
            'phone1_type' => $contractor->phone1_type, 'phone2' => '+1 555 010 9999', 'phone2_type' => $contractor->phone2_type,
            'email' => $contractor->email, 'services' => [3], 'profile_photo' => UploadedFile::fake()->image('profile.png'),
        ])->assertRedirect();
        $this->get(route('users.profile-photo', 2))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->delete(route('admin.contractors.photo.destroy', 2))->assertRedirect();
        $this->assertNull($contractor->fresh()->profile_photo);
        $this->get(route('users.profile-photo', 2))->assertNotFound();
    }

    public function test_m4_screen_routes_render_and_contractor_ids_are_scoped(): void
    {
        $this->signInAs(1);
        foreach ([
            route('admin.contractors.create'), route('admin.contractors.show', 2), route('admin.contractors.edit', 2),
            route('admin.contractors.documents.index', 2), route('admin.services.index'), route('admin.services.create'),
            route('admin.services.edit', 3),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->signInAs(2);
        $this->get(route('contractor.profile.edit', 2))->assertOk();
        $this->get(route('contractor.documents.index', 2))->assertOk();
        $this->get(route('contractor.profile.edit', 1))->assertForbidden();
        $this->get(route('contractor.documents.index', 1))->assertForbidden();
        $this->get(route('admin.contractors.show', 1))->assertForbidden();
    }
}
