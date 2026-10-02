<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Hidden(['path'])]
class PrivateUpload extends LegacyRecord
{
    protected $table = 'app_private_uploads';

    protected $guarded = [];

    public $timestamps = true;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id', 'user_id');
    }

    protected function casts(): array
    {
        return ['byte_size' => 'integer', 'expiry_date' => 'date'];
    }
}
