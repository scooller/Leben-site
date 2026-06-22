<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs\Pages;

use AlizHarb\ActivityLog\Resources\ActivityLogs\Pages\ViewActivityLog as BaseViewActivityLog;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;

class ViewActivityLog extends BaseViewActivityLog
{
    protected static string $resource = ActivityLogResource::class;
}
