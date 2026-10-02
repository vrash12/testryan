<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\JobMedia;
use App\Models\NotificationEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\IdentityTestCase;

class MediaUploadRetryTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Queue::fake();
        config(['notifications.inbox_enabled' => true]);
        Job::whereKey(5)->update(['is_accepted' => 1, 'accepted_datetime' => now(), 'is_completed' => 0]);
        $this->signInAs(3);
    }

    private function payload(string $id, string $text = 'Synthetic file', string $tag = 'Before'): array
    {
        return ['upload_id' => $id, 'tag' => $tag, 'media' => [UploadedFile::fake()->createWithContent('fixture.pdf', "%PDF-1.4\n".$text."\n%%EOF")]];
    }

    public function test_retry_returns_same_ids_without_repeating_files_events_or_audit(): void
    {
        $id = (string) Str::uuid();
        $url = route('contractor.jobs.media.store', 5);
        $first = $this->postJson($url, $this->payload($id))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $counts = [JobMedia::count(), NotificationEvent::count(), DB::table('ActivityLogs')->count(), count(Storage::disk('private')->allFiles())];
        $this->postJson($url, $this->payload($id))->assertOk()->assertExactJson($first->json());
        $this->assertSame($counts, [JobMedia::count(), NotificationEvent::count(), DB::table('ActivityLogs')->count(), count(Storage::disk('private')->allFiles())]);
        $this->assertSame(1, DB::table('app_media_upload_receipts')->count());
        $this->postJson($url, $this->payload($id, 'Changed bytes'))->assertConflict();
        $this->postJson($url, $this->payload($id, tag: 'After'))->assertConflict();
        $this->assertSame($counts[0], JobMedia::count());
    }

    public function test_retries_recheck_assignment_approval_acceptance_and_deleted_media(): void
    {
        $id = (string) Str::uuid();
        $url = route('contractor.jobs.media.store', 5);
        $result = $this->postJson($url, $this->payload($id))->assertOk();
        Job::whereKey(5)->update(['contractor_id' => 2]);
        $this->postJson($url, $this->payload($id))->assertForbidden();
        Job::whereKey(5)->update(['contractor_id' => 3, 'is_accepted' => 0]);
        $this->postJson($url, $this->payload($id))->assertForbidden();
        Job::whereKey(5)->update(['is_accepted' => 1]);
        DB::table('Users')->where('user_id', 3)->update(['is_approved' => 2]);
        $this->postJson($url, $this->payload($id))->assertForbidden();
        DB::table('Users')->where('user_id', 3)->update(['is_approved' => 1]);
        JobMedia::whereKey($result->json('media_ids.0'))->delete();
        $this->postJson($url, $this->payload($id))->assertConflict();
    }

    public function test_invalid_file_does_not_undo_other_successes_or_consume_its_retry_reference(): void
    {
        $url = route('contractor.jobs.media.store', 5);
        $goodId = (string) Str::uuid();
        $badId = (string) Str::uuid();
        $success = $this->postJson($url, $this->payload($goodId))->assertOk();
        $this->postJson($url, ['upload_id' => $badId, 'tag' => 'Before', 'media' => [UploadedFile::fake()->create('bad.heic', 5, 'image/heic')]])->assertUnprocessable();
        $this->assertDatabaseHas('Job_Media', ['media_id' => $success->json('media_ids.0')]);
        $this->assertSame(1, DB::table('app_media_upload_receipts')->count());
        $this->postJson($url, $this->payload($badId))->assertOk();
        $this->assertSame(2, DB::table('app_media_upload_receipts')->count());
        $this->postJson($url, array_replace($this->payload($badId), ['upload_id' => null]))->assertUnprocessable();
        $this->postJson($url, $this->payload('not-a-uuid'))->assertUnprocessable();
    }

    public function test_storage_failure_rolls_back_receipt_and_can_be_retried(): void
    {
        $id = (string) Str::uuid();
        $url = route('contractor.jobs.media.store', 5);
        $before = JobMedia::count();
        $filesystem = Storage::getFacadeRoot();
        Storage::shouldReceive('disk')->with('private')->once()->andReturn(new class
        {
            public function put($path, $bytes): bool
            {
                return false;
            }
        });
        $this->postJson($url, $this->payload($id))->assertServerError();
        $this->assertSame(0, DB::table('app_media_upload_receipts')->count());
        $this->assertSame($before, JobMedia::count());
        Storage::swap($filesystem);
        $this->postJson($url, $this->payload($id))->assertOk();
        $this->assertSame($before + 1, JobMedia::count());
    }

    public function test_separate_files_in_one_selection_share_one_notification(): void
    {
        $url = route('contractor.jobs.media.store', 5);
        $group = (string) Str::uuid();
        $first = array_replace($this->payload((string) Str::uuid()), ['upload_group' => $group]);
        $second = array_replace($this->payload((string) Str::uuid(), 'Second file'), ['upload_group' => $group]);
        $before = JobMedia::count();
        $this->postJson($url, $first)->assertOk();
        $this->postJson($url, $second)->assertOk();
        $this->assertSame($before + 2, JobMedia::count());
        $this->assertSame(1, NotificationEvent::where('type', 'job.media_uploaded')->count());
        $this->postJson($url, array_replace($this->payload($first['upload_id']), ['upload_group' => $group]))->assertOk();
        $this->postJson($url, array_replace($this->payload($first['upload_id']), ['upload_group' => (string) Str::uuid()]))->assertConflict();
        $this->assertSame(1, NotificationEvent::where('type', 'job.media_uploaded')->count());
    }

    public function test_admin_upload_and_non_javascript_form_remain_available(): void
    {
        $this->signInAs(1);
        $id = (string) Str::uuid();
        $this->postJson(route('admin.jobs.media.store', 5), $this->payload($id))->assertOk();
        $this->postJson(route('admin.jobs.media.store', 5), $this->payload($id))->assertOk();
        $payload = $this->payload((string) Str::uuid());
        unset($payload['upload_id']);
        $this->post(route('admin.jobs.media.store', 5), $payload)->assertRedirect()->assertSessionHas('status', 'Job media uploaded.');
        $this->get(route('admin.jobs.show', 5))->assertOk()->assertSee('data-async-media', false)->assertSee('Upload / retry remaining');
    }
}
