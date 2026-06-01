<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs\Pages;

use AlizHarb\ActivityLog\Resources\ActivityLogs\Pages\ListActivityLogs as BaseListActivityLogs;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;

class ListActivityLogs extends BaseListActivityLogs
{
    protected static string $resource = ActivityLogResource::class;
}
