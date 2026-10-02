<?php

namespace App\Policies;

use App\Models\PrivateUpload;
use App\Models\User;

class PrivateUploadPolicy extends DomainPolicy
{
    public function view(User $user, PrivateUpload $upload): bool
    {
        if ($upload->disk !== 'private') {
            return false;
        }

        return $this->admin($user) || $this->owner($user, (int) $upload->owner_user_id);
    }

    public function delete(User $user, PrivateUpload $upload): bool
    {
        return $this->admin($user);
    }
}
