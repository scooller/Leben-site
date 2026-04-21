<?php

namespace App\Filament\Resources\ContactChannels\Pages;

use App\Filament\Resources\ContactChannels\ContactChannelResource;
use Filament\Resources\Pages\ListRecords;

class ListContactChannels extends ListRecords
{
    protected static string $resource = ContactChannelResource::class;
}
