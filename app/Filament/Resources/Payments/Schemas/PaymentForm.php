<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('gateway')
                    ->required(),
                TextInput::make('gateway_tx_id'),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('CLP'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                Textarea::make('metadata')
                    ->columnSpanFull(),
                DateTimePicker::make('completed_at'),
            ]);
    }
}
