<?php

namespace App\Models;

use App\Actions\Identity\IdentityMessages;
use App\Enums\ApprovalStatus;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([])]
#[Hidden(['password_hash', 'profile_photo', 'accountState'])]
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $table = 'Users';

    protected $primaryKey = 'user_id';

    public const UPDATED_AT = null;

    protected $authPasswordName = 'password_hash';

    // Legacy Users has no remember_token. Persistent login is deferred.
    protected $rememberTokenName = null;

    public function accountState(): HasOne
    {
        return $this->hasOne(AccountState::class, 'user_id', 'user_id');
    }

    public function isActiveAccount(): bool
    {
        return $this->accountState?->blocked_at === null && $this->accountState?->archived_at === null;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email !== null && $this->accountState?->email_verified_at !== null
            && hash_equals(strtolower(trim($this->email)), strtolower(trim($this->accountState->verified_email ?? '')));
    }

    public function markEmailAsVerified(): bool
    {
        $state = $this->accountState()->firstOrNew();
        $state->forceFill(['email_verified_at' => now(), 'verified_email' => $this->email])->save();
        $this->unsetRelation('accountState');

        return true;
    }

    public function canAccessWork(): bool
    {
        return $this->role() === 'contractor' && $this->isActiveAccount() && $this->hasVerifiedEmail() && $this->is_approved === 1;
    }

    public function sendEmailVerificationNotification(): void
    {
        app(IdentityMessages::class)->verification($this);
    }

    public function sendPasswordResetNotification($token): void
    {
        app(IdentityMessages::class)->recovery($this, $token);
    }

    public function approvalStatus(): ?ApprovalStatus
    {
        return ApprovalStatus::tryFrom($this->is_approved ?? -1);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'ContractorServices', 'user_id', 'service_id', 'user_id', 'service_id')->using(ContractorService::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'contractor_id', 'user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContractorDocument::class, 'user_id', 'user_id');
    }

    public function contractorNotes(): HasMany
    {
        return $this->hasMany(ContractorNote::class, 'user_id', 'user_id');
    }

    public function authoredContractorNotes(): HasMany
    {
        return $this->hasMany(ContractorNote::class, 'admin_id', 'user_id');
    }

    public function jobNotes(): HasMany
    {
        return $this->hasMany(JobNote::class, 'created_by', 'user_id');
    }

    public function uploadedMedia(): HasMany
    {
        return $this->hasMany(JobMedia::class, 'uploaded_by', 'user_id');
    }

    public function activityLogs(): HasMany
    {
        // This is a legacy association, not a guarantee that the user was the actor.
        return $this->hasMany(ActivityLog::class, 'user_id', 'user_id');
    }

    public function privatePhoto(): HasOne
    {
        return $this->hasOne(ImportedPrivateFile::class, 'source_id', 'user_id')->where('source_table', 'Users')->where('source_column', 'profile_photo');
    }

    public function profileUpload(): HasOne
    {
        return $this->hasOne(PrivateUpload::class, 'source_id', 'user_id')
            ->where('source_table', 'Users')
            ->where('source_column', 'profile_photo')
            ->where('kind', 'profile_photo')
            ->latestOfMany();
    }

    public function privateUploads(): HasMany
    {
        return $this->hasMany(PrivateUpload::class, 'owner_user_id', 'user_id');
    }

    public function role(): ?string
    {
        return match ($this->is_admin) {
            1 => 'admin',
            0 => 'contractor',
            default => null,
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_admin' => 'integer',
            'is_approved' => 'integer',
            'approved_date' => 'datetime',
        ];
    }
}
