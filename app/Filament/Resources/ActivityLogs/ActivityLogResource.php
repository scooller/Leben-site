<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs;

use AlizHarb\ActivityLog\Resources\ActivityLogs\ActivityLogResource as BaseActivityLogResource;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Filament\Resources\ActivityLogs\Schemas\ActivityLogInfolist;
use Filament\Schemas\Schema;

class ActivityLogResource extends BaseActivityLogResource
{
    public static function infolist(Schema $schema): Schema
    {
        return ActivityLogInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }
}
