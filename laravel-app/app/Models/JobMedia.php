<?php

namespace App\Models;

use App\Enums\MediaTag;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Hidden(['file_data'])]
class JobMedia extends LegacyRecord
{
    protected $table = 'Job_Media';

    protected $primaryKey = 'media_id';

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id', 'job_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }

    public function privateFile(): HasOne
    {
        return $this->hasOne(ImportedPrivateFile::class, 'source_id', 'media_id')->where('source_table', 'Job_Media')->where('source_column', 'file_data');
    }

    public function privateUpload(): HasOne
    {
        return $this->hasOne(PrivateUpload::class, 'source_id', 'media_id')
            ->where('source_table', 'Job_Media')
            ->where('source_column', 'file_data')
            ->where('kind', 'job_media')
            ->latestOfMany();
    }

    protected function casts(): array
    {
        return ['upload_datetime' => 'datetime', 'tag' => MediaTag::class];
    }
}
