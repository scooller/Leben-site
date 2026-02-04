<?php

namespace App\Filament\Resources\SalesforceLeads\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SalesforceLeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('salesforce_id')
                    ->required(),
                TextInput::make('first_name'),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('company'),
                TextInput::make('status'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('country'),
                Textarea::make('raw_data')
                    ->columnSpanFull(),
                DateTimePicker::make('last_synced_at'),
            ]);
    }
}
