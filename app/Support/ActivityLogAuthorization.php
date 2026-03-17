<?php

namespace App\Support;

use App\Models\User;

class ActivityLogAuthorization
{
    public function __invoke(mixed $user): bool
    {
        return $user instanceof User && $user->isAdmin();
    }
}
