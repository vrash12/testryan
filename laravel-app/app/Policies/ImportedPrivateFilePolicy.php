<?php

namespace App\Policies;

use App\Models\ContractorDocument;
use App\Models\ImportedPrivateFile;
use App\Models\JobMedia;
use App\Models\User;

class ImportedPrivateFilePolicy extends DomainPolicy
{
    public function view(User $user, ImportedPrivateFile $file): bool
    {
        if ($file->disk !== 'private') {
            return false;
        }
        $record = match ($file->source_table.':'.$file->source_column) {
            'Users:profile_photo' => User::find($file->source_id),
            'Files:file_data' => ContractorDocument::find($file->source_id),
            'Job_Media:file_data' => JobMedia::find($file->source_id),
            default => null,
        };

        return match (true) {
            $record instanceof User => app(UserPolicy::class)->view($user, $record),
            $record instanceof ContractorDocument => app(ContractorDocumentPolicy::class)->view($user, $record),
            $record instanceof JobMedia => app(JobMediaPolicy::class)->view($user, $record),
            default => false,
        };
    }
}
