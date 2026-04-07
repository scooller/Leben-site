<?php

namespace App\Observers;

use App\Support\BusinessActivityLogger;
use Laravel\Sanctum\PersonalAccessToken;

class PersonalAccessTokenObserver
{
    public function created(PersonalAccessToken $token): void
    {
        BusinessActivityLogger::logApiTokenCreated($token);
    }

    public function deleted(PersonalAccessToken $token): void
    {
        BusinessActivityLogger::logApiTokenRevoked($token);
    }
}
