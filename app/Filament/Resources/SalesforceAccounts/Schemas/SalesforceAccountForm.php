<?php

namespace App\Filament\Resources\SalesforceAccounts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SalesforceAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('salesforce_id')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('type'),
                TextInput::make('billing_country'),
                TextInput::make('billing_city'),
                TextInput::make('industry'),
                Textarea::make('raw_data')
                    ->columnSpanFull(),
                DateTimePicker::make('last_synced_at'),
            ]);
    }
}
