<?php

namespace App\Actions\Identity;

use App\Models\AccountState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterContractor
{
    public function run(array $data, string $ip): User
    {
        $passwordHash = Hash::make($data['password']);

        return DB::transaction(function () use ($data, $ip, $passwordHash): User {
            $company = Str::lower(preg_replace('/\s+/u', ' ', trim($data['company_name'])));
            $duplicateCompany = User::whereRaw('LOWER(TRIM(company_name)) = ?', [$company])->exists();
            $user = (new User)->forceFill([
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'company_name' => $data['company_name'],
                'company_address' => $data['company_address'], 'company_phone' => $data['company_phone'],
                'phone1' => $data['phone1'] ?? null, 'phone1_type' => $data['phone1_type'] ?? '',
                'phone2' => $data['phone2'] ?? '', 'phone2_type' => $data['phone2_type'] ?? '',
                'email' => $data['email'], 'password_hash' => $passwordHash, 'priority' => 0,
                'is_admin' => 0, 'is_approved' => 0, 'approved_date' => null,
            ]);
            $user->save();
            $signals = $duplicateCompany ? ['matching_company_name'] : [];
            if (empty($data['services'])) {
                $signals[] = 'no_services_selected';
            }
            (new AccountState)->forceFill(['user_id' => $user->user_id, 'session_version' => 0, 'registration_signals' => $signals,
                'registration_ip_hash' => hash_hmac('sha256', $ip, config('app.key'))])->save();
            $user->services()->sync($data['services'] ?? []);
            app(SecurityAudit::class)->record($user, 'self-registration; approval pending.');
            app(IdentityMessages::class)->verification($user);
            app(IdentityMessages::class)->registration($user);

            return $user;
        });
    }
}
