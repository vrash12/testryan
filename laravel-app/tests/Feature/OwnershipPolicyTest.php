<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ContractorDocument;
use App\Models\ContractorNote;
use App\Models\ImportedPrivateFile;
use App\Models\Job;
use App\Models\JobMedia;
use App\Models\JobNote;
use App\Models\Service;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\IdentityTestCase;

class OwnershipPolicyTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $run = (string) Str::ulid();
        DB::table('app_import_runs')->insert(['id' => $run, 'source_sha256' => str_repeat('0', 64), 'text_encoding' => 'utf8', 'report' => '{}', 'completed_at' => now()]);
        DB::table('app_private_files')->insert(['id' => 1, 'import_run_id' => $run, 'source_table' => 'Users', 'source_id' => 2, 'source_column' => 'profile_photo', 'disk' => 'private', 'path' => 'fixture.bin', 'byte_size' => 1, 'sha256' => hash('sha256', 'x'), 'detected_mime_type' => 'image/png', 'needs_review' => false]);
        $models = ['profile' => User::class, 'job' => Job::class, 'document' => ContractorDocument::class, 'media' => JobMedia::class, 'job-note' => JobNote::class, 'private-file' => ImportedPrivateFile::class, 'private-note' => ContractorNote::class, 'activity' => ActivityLog::class, 'template' => Template::class, 'service' => Service::class];
        // These probes exist only in the tests; M4/M5 resource HTTP routes are not implemented yet.
        Route::get('/_policy/{kind}/{id}', function (string $kind, int $id) use ($models) {
            abort_unless(isset($models[$kind]), 404);
            Gate::authorize('view', $models[$kind]::findOrFail($id));

            return response()->json(['authorized' => true]);
        })->middleware(['web', 'auth', 'account.current', 'verified']);
        Route::get('/_policy-media/{job}/{media}', function (int $job, int $media) {
            Gate::authorize('view', [JobMedia::findOrFail($media), Job::findOrFail($job)]);

            return response()->json(['authorized' => true]);
        })->middleware(['web', 'auth', 'account.current', 'verified']);
    }

    public function test_every_sensitive_resource_denies_cross_contractor_http_requests_and_allows_admin(): void
    {
        $owned = ['profile' => 2, 'job' => 4, 'document' => 2, 'media' => 5, 'job-note' => 1, 'private-file' => 1];
        $this->signInAs(2);
        foreach ($owned as $kind => $id) {
            $this->get('/_policy/'.$kind.'/'.$id)->assertOk()->assertExactJson(['authorized' => true]);
        }
        foreach (['private-note' => 1, 'activity' => 1, 'template' => 2] as $kind => $id) {
            $this->get('/_policy/'.$kind.'/'.$id)->assertForbidden();
        }
        $this->get('/_policy/service/3')->assertOk();
        $this->signInAs(3);
        foreach ($owned as $kind => $id) {
            $this->get('/_policy/'.$kind.'/'.$id)->assertForbidden();
        }
        $this->signInAs(1);
        foreach ($owned + ['private-note' => 1, 'activity' => 1, 'template' => 2, 'service' => 3] as $kind => $id) {
            $this->get('/_policy/'.$kind.'/'.$id)->assertOk();
        }
        $this->assertFalse(Gate::allows('delete', ActivityLog::findOrFail(1)));
    }

    public function test_nested_parent_reassignment_review_and_media_acceptance_are_rechecked(): void
    {
        $user = $this->signInAs(2);
        $this->get('/_policy-media/4/5')->assertOk();
        $this->get('/_policy-media/5/5')->assertForbidden();
        $this->assertTrue(Gate::allows('uploadMedia', Job::findOrFail(4)));
        DB::table('Jobs')->where('job_id', 4)->update(['is_accepted' => 0]);
        $this->assertFalse(Gate::allows('uploadMedia', Job::findOrFail(4)));
        $this->assertFalse(Gate::allows('create', [JobNote::class, Job::findOrFail(4)]));
        DB::table('Jobs')->where('job_id', 4)->update(['contractor_id' => 3]);
        $this->get('/_policy/media/5')->assertForbidden();
        $this->get('/_policy/job-note/1')->assertForbidden();
        User::where('user_id', 2)->update(['is_approved' => 2]);
        $this->get('/_policy/document/2')->assertForbidden();
        $this->get('/_policy/profile/2')->assertForbidden();
        $this->signInAs(1);
        $this->get('/_policy-media/5/5')->assertForbidden();
        DB::table('app_private_files')->where('id', 1)->update(['disk' => 'public']);
        $this->get('/_policy/private-file/1')->assertForbidden();
    }
}
