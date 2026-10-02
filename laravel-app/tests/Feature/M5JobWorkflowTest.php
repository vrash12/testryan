<?php

namespace Tests\Feature;

use App\Actions\Job\JobManagement;
use App\Enums\MediaTag;
use App\Models\Job;
use App\Models\JobMedia;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\IdentityTestCase;

class M5JobWorkflowTest extends IdentityTestCase
{
    public function test_admin_creates_edits_and_assigns_jobs_with_decimal_and_service_validation(): void
    {
        $this->signInAs(1);
        DB::table('Services')->insert(['service_id' => 4, 'service_name' => 'Second fixture service']);

        $this->post(route('admin.jobs.store'), $this->jobPayload([
            'approved_price' => '1250.05', 'deposit_amount' => '0.00',
        ]))->assertRedirect();
        $job = Job::where('job_name', 'M5 fixture job')->firstOrFail();
        $this->assertSame('1250.05', $job->approved_price);
        $this->assertSame('0.00', $job->deposit_amount);
        $this->assertNull($job->contractor_id);
        $this->assertNull($job->accepted_datetime);
        $this->assertSame([3], $job->services()->pluck('Services.service_id')->map(fn ($id): int => (int) $id)->all());

        $this->put(route('admin.jobs.update', $job), $this->jobPayload([
            'job_name' => 'M5 edited job', 'services' => [4], 'approved_price' => '1250.25', 'deposit_amount' => '250.25',
        ]))->assertRedirect(route('admin.jobs.show', $job));
        $job->refresh();
        $this->assertSame('1250.25', $job->approved_price);
        $this->assertSame('250.25', $job->deposit_amount);
        $this->assertSame([4], $job->services()->pluck('Services.service_id')->map(fn ($id): int => (int) $id)->all());

        $this->put(route('admin.jobs.update', $job), $this->jobPayload([
            'approved_price' => '10.00', 'deposit_amount' => '10.01',
        ]))->assertSessionHasErrors('deposit_amount');
        $this->assertSame('1250.25', $job->fresh()->approved_price);
        $this->post(route('admin.jobs.store'), $this->jobPayload(['services' => [9999]]))->assertSessionHasErrors('services.0');
        $this->post(route('admin.jobs.store'), $this->jobPayload(['approved_price' => '12.345']))->assertSessionHasErrors('approved_price');

        $this->post(route('admin.jobs.assignment', $job), ['contractor_id' => 2])->assertRedirect();
        $this->assertSame(2, $job->fresh()->contractor_id);
        $this->post(route('admin.jobs.assignment', $job), ['contractor_id' => 1])->assertSessionHasErrors('contractor_id');
        DB::table('Users')->where('user_id', 3)->update(['is_approved' => 0]);
        $this->post(route('admin.jobs.assignment', $job), ['contractor_id' => 3])->assertSessionHasErrors('contractor_id');
        $this->assertSame(2, $job->fresh()->contractor_id);
        $this->assertDatabaseHas('ActivityLogs', ['user_id' => 1]);
        $this->assertGreaterThanOrEqual(1, DB::table('app_job_transitions')->where('job_id', $job->job_id)->count());
    }

    public function test_admin_can_create_an_accepted_assignment_and_acceptance_completion_are_idempotent(): void
    {
        $this->signInAs(1);
        $this->travelTo(now()->startOfSecond());
        $this->post(route('admin.jobs.store'), $this->jobPayload([
            'job_name' => 'Accepted on creation', 'contractor_id' => 2, 'accept_on_behalf' => 1,
        ]))->assertRedirect();
        $job = Job::where('job_name', 'Accepted on creation')->firstOrFail();
        $this->assertSame(2, $job->contractor_id);
        $this->assertSame(1, $job->is_accepted);
        $this->assertNotNull($job->accepted_datetime);
        $acceptedAt = $job->accepted_datetime->format('Y-m-d H:i:s');
        $historyCount = DB::table('app_job_transitions')->where('job_id', $job->job_id)->count();

        $this->travel(1)->minutes();
        $this->post(route('admin.jobs.acceptance', $job))->assertRedirect();
        $this->assertSame($acceptedAt, $job->fresh()->accepted_datetime->format('Y-m-d H:i:s'));
        $this->assertSame($historyCount, DB::table('app_job_transitions')->where('job_id', $job->job_id)->count());

        $this->post(route('admin.jobs.completion', $job), ['completed' => 1])->assertRedirect();
        $job->refresh();
        $this->assertSame(1, $job->is_completed);
        $completedAt = $job->completed_datetime->format('Y-m-d H:i:s');
        $historyCount = DB::table('app_job_transitions')->where('job_id', $job->job_id)->count();
        $this->travel(1)->minutes();
        $this->post(route('admin.jobs.completion', $job), ['completed' => 1])->assertRedirect();
        $this->assertSame($completedAt, $job->fresh()->completed_datetime->format('Y-m-d H:i:s'));
        $this->assertSame($historyCount, DB::table('app_job_transitions')->where('job_id', $job->job_id)->count());

        $this->post(route('admin.jobs.assignment', $job), ['contractor_id' => 3])->assertSessionHasErrors();
        $this->assertSame(2, $job->fresh()->contractor_id);
        $this->post(route('admin.jobs.completion', $job), ['completed' => 0])->assertRedirect();
        $job->refresh();
        $this->assertSame(0, $job->is_completed);
        $this->assertNull($job->completed_datetime);
        $this->assertSame($acceptedAt, $job->accepted_datetime->format('Y-m-d H:i:s'));

        DB::table('Users')->where('user_id', 3)->update(['is_approved' => 1]);
        $this->post(route('admin.jobs.assignment', $job), ['contractor_id' => 3])->assertRedirect();
        $job->refresh();
        $this->assertSame(3, $job->contractor_id);
        $this->assertSame(0, $job->is_accepted);
        $this->assertSame(0, $job->is_completed);
        $this->assertNull($job->accepted_datetime);
        $this->assertNull($job->completed_datetime);
        $transitions = DB::table('app_job_transitions')->where('job_id', $job->job_id)->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(3, $transitions->count());
        $this->assertSame(1, (int) $transitions->last()->actor_user_id);
        $this->assertSame('pending', $transitions->last()->to_status);
        $this->assertSame(3, (int) $transitions->last()->to_contractor_id);
        $this->travelBack();
    }

    public function test_contractor_assignment_approval_and_completion_permissions_are_enforced(): void
    {
        Storage::fake('private');
        $this->pendingJob(4, 2);
        $this->signInAs(2);
        $this->get(route('contractor.jobs.index'))->assertOk()->assertSee('Fixture job');
        $this->get(route('contractor.jobs.show', 4))->assertOk();
        $this->get(route('contractor.jobs.show', 5))->assertForbidden();
        $this->post(route('contractor.jobs.acceptance', 5))->assertForbidden();
        $this->post(route('contractor.jobs.notes.store', 5), ['note_text' => 'Cross owner'])->assertForbidden();
        $this->post(route('contractor.jobs.media.store', 4), ['tag' => 'Before', 'media' => [UploadedFile::fake()->image('before.png')]])->assertForbidden();
        $this->post(route('admin.jobs.completion', 4), ['completed' => 1])->assertForbidden();

        $this->post(route('contractor.jobs.acceptance', 4))->assertRedirect();
        $acceptedAt = Job::findOrFail(4)->accepted_datetime->format('Y-m-d H:i:s');
        $this->post(route('contractor.jobs.acceptance', 4))->assertRedirect();
        $this->assertSame($acceptedAt, Job::findOrFail(4)->accepted_datetime->format('Y-m-d H:i:s'));

        $this->signInAs(1);
        $this->post(route('admin.jobs.completion', 4), ['completed' => 1])->assertRedirect();
        $this->signInAs(2);
        $this->get(route('contractor.jobs.show', 4))->assertOk();
        $this->post(route('contractor.jobs.notes.store', 4), ['note_text' => 'Completed write'])->assertForbidden();
        $this->post(route('contractor.jobs.media.store', 4), ['tag' => 'After', 'media' => [UploadedFile::fake()->image('after.png')]])->assertForbidden();

        DB::table('Users')->where('user_id', 2)->update(['is_approved' => 2]);
        $this->signInAs(2);
        $this->get(route('contractor.jobs.index'))->assertForbidden();
        $this->get(route('job-media.preview', 5))->assertForbidden();
    }

    public function test_shared_job_notes_are_append_only_and_html_is_rendered_safely(): void
    {
        $this->pendingJob(4, 2);
        $this->signInAs(2);
        $this->post(route('contractor.jobs.notes.store', 4), ['note_text' => '<script>alert("note")</script> A question before acceptance.'])->assertRedirect();
        $this->assertDatabaseHas('Job_Notes', ['job_id' => 4, 'created_by' => 2, 'note_text' => '<script>alert("note")</script> A question before acceptance.']);
        $this->post(route('contractor.jobs.notes.store', 4), ['note_text' => '   '])->assertSessionHasErrors('note_text');
        $this->get(route('contractor.jobs.show', 4))->assertOk()
            ->assertSee('A question before acceptance.')
            ->assertDontSee('Private fixture note')
            ->assertDontSee('<script>alert("note")</script>', false)
            ->assertDontSee('<script>kept in storage</script>', false);

        $this->signInAs(1);
        $this->post(route('admin.jobs.notes.store', 4), ['note_text' => 'Shared administrator answer.'])->assertRedirect();
        $this->signInAs(2);
        $this->get(route('contractor.jobs.show', 4))->assertSee('Shared administrator answer.');
        $this->signInAs(3);
        $this->get(route('contractor.jobs.show', 4))->assertForbidden();

        $this->signInAs(1);
        DB::table('Users')->where('user_id', 3)->update(['is_approved' => 1]);
        DB::table('Users')->where('user_id', 3)->update(['email' => 'third@example.test']);
        DB::table('app_account_states')->where('user_id', 3)->update(['email_verified_at' => now(), 'verified_email' => 'third@example.test']);
        $this->post(route('admin.jobs.assignment', 4), ['contractor_id' => 3])->assertRedirect();
        $this->signInAs(2);
        $this->get(route('contractor.jobs.show', 4))->assertForbidden();
        $this->signInAs(3);
        $this->get(route('contractor.jobs.show', 4))->assertSee('Shared administrator answer.');
        $this->assertDatabaseHas('Job_Notes', ['job_id' => 4, 'created_by' => 2]);
    }

    public function test_job_media_is_validated_private_and_scoped_to_the_current_assignment(): void
    {
        Storage::fake('private');
        $this->pendingJob(4, 2);
        $this->signInAs(2);
        $this->post(route('contractor.jobs.acceptance', 4))->assertRedirect();
        $this->post(route('contractor.jobs.media.store', 4), [
            'tag' => 'Before', 'media' => [UploadedFile::fake()->image('job-before.png', 640, 480), UploadedFile::fake()->create('job-plan.pdf', 12, 'application/pdf')],
        ])->assertRedirect();
        $photo = JobMedia::where('file_name', 'job-before.png')->firstOrFail();
        $pdf = JobMedia::where('file_name', 'job-plan.pdf')->firstOrFail();
        $upload = $photo->privateUpload;
        $this->assertNotNull($upload);
        $this->assertSame(MediaTag::Before, $photo->tag);
        $this->assertSame(2, (int) $photo->uploaded_by);
        Storage::disk('private')->assertExists($upload->path);
        $this->assertSame(hash('sha256', Storage::disk('private')->get($upload->path)), $upload->sha256);
        $this->assertSame(strlen(Storage::disk('private')->get($upload->path)), $upload->byte_size);
        $this->get(route('job-media.download', $photo))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="job-before.png"');
        $this->get(route('job-media.preview', $pdf))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $thumbnail = $this->get(route('job-media.thumbnail', $photo))->assertOk();
        $this->assertStringStartsWith('image/', $thumbnail->headers->get('Content-Type'));
        $thumbnailBytes = $thumbnail->baseResponse instanceof BinaryFileResponse
            ? file_get_contents($thumbnail->baseResponse->getFile()->getPathname())
            : $thumbnail->getContent();
        $dimensions = getimagesizefromstring($thumbnailBytes);
        $this->assertNotFalse($dimensions);
        $this->assertLessThanOrEqual(120, $dimensions[0]);
        $this->assertLessThanOrEqual(120, $dimensions[1]);
        $this->get(route('job-media.thumbnail', $pdf))->assertNotFound();

        $before = JobMedia::count();
        $this->post(route('contractor.jobs.media.store', 4), ['tag' => 'Invalid', 'media' => [UploadedFile::fake()->image('invalid-tag.png')]])->assertSessionHasErrors('tag');
        $this->post(route('contractor.jobs.media.store', 4), ['tag' => 'During', 'media' => [UploadedFile::fake()->create('invalid.jpg', 2, 'text/plain')]])->assertSessionHasErrors('media.0');
        $this->post(route('contractor.jobs.media.store', 4), ['tag' => 'During', 'media' => [UploadedFile::fake()->image('too-large.png')->size(10241)]])->assertSessionHasErrors('media.0');
        $this->assertSame($before, JobMedia::count());

        $this->signInAs(3);
        $this->get(route('job-media.preview', $photo))->assertForbidden();
        $this->get(route('job-media.download', $photo))->assertForbidden();
        $this->get(route('job-media.thumbnail', $photo))->assertForbidden();
        $this->signInAs(2);
        $this->delete(route('admin.jobs.media.destroy', [4, $photo]))->assertForbidden();
        $this->signInAs(1);
        $this->delete(route('admin.jobs.media.destroy', [5, $photo]))->assertNotFound();
        $this->assertDatabaseHas('Job_Media', ['media_id' => $photo->media_id]);
        $this->delete(route('admin.jobs.media.destroy', [4, $photo]))->assertRedirect();
        $this->assertDatabaseMissing('Job_Media', ['media_id' => $photo->media_id]);
        $this->assertDatabaseMissing('app_private_uploads', ['source_table' => 'Job_Media', 'source_id' => $photo->media_id]);
        Storage::disk('private')->assertMissing($upload->path);
    }

    public function test_job_delete_removes_owned_children_and_storage_but_retains_transition_and_activity_history(): void
    {
        Storage::fake('private');
        $this->signInAs(1);
        $this->post(route('admin.jobs.store'), $this->jobPayload(['job_name' => 'Delete M5 job']))->assertRedirect();
        $job = Job::where('job_name', 'Delete M5 job')->firstOrFail();
        $this->post(route('admin.jobs.notes.store', $job), ['note_text' => 'To be removed with the job.'])->assertRedirect();
        $this->post(route('admin.jobs.media.store', $job), ['tag' => 'Other', 'media' => [UploadedFile::fake()->image('delete-job.png')]])->assertRedirect();
        $media = $job->media()->firstOrFail();
        $upload = $media->privateUpload;
        $historyBefore = DB::table('app_job_transitions')->where('job_id', $job->job_id)->count();
        $this->delete(route('admin.jobs.destroy', $job))->assertRedirect(route('admin.jobs.index'));
        $this->assertDatabaseMissing('Jobs', ['job_id' => $job->job_id]);
        $this->assertDatabaseMissing('Job_Notes', ['job_id' => $job->job_id]);
        $this->assertDatabaseMissing('Job_Media', ['job_id' => $job->job_id]);
        $this->assertDatabaseMissing('Job_Services', ['job_id' => $job->job_id]);
        Storage::disk('private')->assertMissing($upload->path);
        $this->assertGreaterThan($historyBefore, DB::table('app_job_transitions')->where('job_id', $job->job_id)->count());
        $this->assertTrue(DB::table('ActivityLogs')->where('user_id', 1)->where('action', 'like', '%deleted job #'.$job->job_id.'%')->exists());
    }

    public function test_m5_screens_render_for_roles_and_stale_assignment_cannot_write_after_reassignment(): void
    {
        Storage::fake('private');
        $this->signInAs(1);
        foreach (['admin.jobs.index', 'admin.jobs.create'] as $name) {
            $this->get(route($name))->assertOk();
        }
        foreach (['admin.jobs.show', 'admin.jobs.edit', 'admin.jobs.print'] as $name) {
            $this->get(route($name, 4))->assertOk();
        }
        $this->pendingJob(4, 2);
        $stale = Job::findOrFail(4);
        $actor = User::findOrFail(2);
        DB::table('Jobs')->where('job_id', 4)->update(['contractor_id' => 3, 'is_accepted' => 1]);
        $this->signInAs(2);
        try {
            app(JobManagement::class)->addNote($actor, $stale, 'Stale assignment note');
            $this->fail('A stale assignment must not authorize a note after reassignment.');
        } catch (AuthorizationException|HttpException $exception) {
            $this->assertSame(403, $exception instanceof HttpException ? $exception->getStatusCode() : 403);
        }
        try {
            app(JobManagement::class)->addMedia($actor, $stale, [UploadedFile::fake()->image('stale.png')], MediaTag::Other);
            $this->fail('A stale assignment must not authorize media after reassignment.');
        } catch (AuthorizationException|HttpException $exception) {
            $this->assertSame(403, $exception instanceof HttpException ? $exception->getStatusCode() : 403);
        }
        $this->assertDatabaseMissing('Job_Notes', ['note_text' => 'Stale assignment note']);
        $this->assertDatabaseMissing('Job_Media', ['file_name' => 'stale.png']);
        DB::table('Users')->where('user_id', 3)->update(['is_approved' => 1, 'email' => 'third@example.test']);
        DB::table('app_account_states')->where('user_id', 3)->update(['email_verified_at' => now(), 'verified_email' => 'third@example.test']);
        $this->signInAs(3);
        $this->get(route('contractor.jobs.show', 4))->assertOk();
        $this->get(route('contractor.jobs.print', 4))->assertOk();
    }

    private function jobPayload(array $overrides = []): array
    {
        return array_replace([
            'job_name' => 'M5 fixture job', 'job_description' => '<p>Fixture scope of work.</p>',
            'terms' => '<p>Fixture jobsite terms.</p>', 'due_date' => now()->addDays(10)->toDateString(),
            'approved_price' => '1200.00', 'deposit_amount' => '200.00', 'who_supplies_materials' => 'Client', 'services' => [3],
        ], $overrides);
    }

    private function pendingJob(int $jobId, int $contractorId): void
    {
        DB::table('Jobs')->where('job_id', $jobId)->update([
            'contractor_id' => $contractorId, 'is_accepted' => 0, 'accepted_datetime' => null,
            'is_completed' => 0, 'completed_datetime' => null,
        ]);
    }
}
