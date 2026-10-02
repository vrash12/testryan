<?php

namespace Tests\Support;

use App\Actions\Import\LocalImportGuard;
use App\Models\AccountState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

abstract class IdentityTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! config('database.connections.m2_test.username')) {
            $this->markTestSkipped('Dedicated local MySQL identity test credentials are not configured.');
        }
        config(['database.default' => 'm2_test', 'legacy.source_connection' => 'm2_source_test', 'legacy.target_connection' => 'm2_test', 'hashing.bcrypt.rounds' => 10, 'mail.default' => 'array']);
        app(LocalImportGuard::class)->check(DB::connection('m2_source_test'), DB::connection('m2_test'));
        $this->artisan('migrate:fresh', ['--database' => 'm2_test', '--force' => true])->assertSuccessful();
        $rows = LegacyFixture::rows();
        $rows['Users'][1]['is_approved'] = 1;
        $rows['Users'][2]['is_approved'] = 1;
        $rows['Users'][2]['email'] = 'other@example.test';
        $rows['Jobs'][] = array_replace($rows['Jobs'][0], ['job_id' => 5, 'contractor_id' => 3, 'is_accepted' => 0, 'is_completed' => 0]);
        foreach ($rows as $table => $records) {
            foreach ($records as $record) {
                DB::table($table)->insert($record);
            }
        }
        foreach (User::all() as $user) {
            (new AccountState)->forceFill(['user_id' => $user->user_id, 'email_verified_at' => now(), 'verified_email' => $user->email, 'session_version' => 0])->save();
        }
        Notification::fake();
        $this->withoutVite();
    }

    protected function signInAs(int $id): User
    {
        $user = User::findOrFail($id);
        $this->actingAs($user)->withSession(['auth_session_version' => $user->accountState->session_version]);

        return $user;
    }

    protected function unverify(int $id): User
    {
        AccountState::where('user_id', $id)->update(['email_verified_at' => null, 'verified_email' => null]);

        return User::findOrFail($id);
    }

    protected function registration(array $overrides = []): array
    {
        $challenge = ['token' => str_repeat('a', 40), 'answer' => '12', 'issued_at' => now()->subSeconds(5)->timestamp];
        $this->withSession(['registration_challenge' => $challenge]);

        return array_replace([
            'first_name' => 'New', 'last_name' => 'Contractor', 'company_name' => 'New Fixture Company', 'company_address' => 'Synthetic street 1',
            'company_phone' => '+1 555 010 0200', 'email' => 'new@example.test', 'password' => 'A-new-fixture-passphrase',
            'password_confirmation' => 'A-new-fixture-passphrase', 'services' => [3], 'bot_token' => $challenge['token'], 'bot_answer' => '12',
        ], $overrides);
    }
}
