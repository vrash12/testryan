<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;

#[Hidden(['path'])]
class ImportedPrivateFile extends LegacyRecord
{
    protected $table = 'app_private_files';

    protected function casts(): array
    {
        return ['byte_size' => 'integer', 'needs_review' => 'boolean'];
    }
}
