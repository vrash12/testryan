<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorNote extends LegacyRecord
{
    protected $table = 'Notes';

    protected $primaryKey = 'note_id';

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function adminAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id', 'user_id');
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
