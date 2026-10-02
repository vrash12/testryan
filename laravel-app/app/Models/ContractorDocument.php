<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Hidden(['file_data'])]
class ContractorDocument extends LegacyRecord
{
    protected $table = 'Files';

    protected $primaryKey = 'file_id';

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function privateFile(): HasOne
    {
        return $this->hasOne(ImportedPrivateFile::class, 'source_id', 'file_id')->where('source_table', 'Files')->where('source_column', 'file_data');
    }

    public function privateUpload(): HasOne
    {
        return $this->hasOne(PrivateUpload::class, 'source_id', 'file_id')
            ->where('source_table', 'Files')
            ->where('source_column', 'file_data')
            ->where('kind', 'document')
            ->latestOfMany();
    }

    protected function casts(): array
    {
        return ['upload_date' => 'datetime', 'expiry_date' => 'date', 'archived' => 'integer'];
    }
}
