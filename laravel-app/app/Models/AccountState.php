<?php

namespace App\Models;

class AccountState extends LegacyRecord
{
    protected $table = 'app_account_states';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $hidden = ['session_version', 'registration_signals', 'registration_ip_hash', 'verified_email'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'session_version' => 'integer', 'blocked_at' => 'datetime', 'archived_at' => 'datetime', 'registration_signals' => 'array'];
    }
}
