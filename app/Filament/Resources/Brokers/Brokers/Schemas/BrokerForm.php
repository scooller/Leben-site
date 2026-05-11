<?php

namespace App\Filament\Resources\Brokers\Brokers\Schemas;

use App\Models\Broker;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BrokerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Perfil Broker')
                    ->schema([
                        TextInput::make('salesforce_id')
                            ->label('Salesforce ID')
                            ->disabled()
                            ->copyable()
                            ->suffixAction(
                                Action::make('openSalesforceBroker')
                                    ->label('Ver en Salesforce')
                                    ->icon('heroicon-m-arrow-top-right-on-square')
                                    ->url(fn (?Broker $record): ?string => filled($record?->salesforce_id)
                                        ? (config('services.salesforce.instance_url') ?? env('SF_INSTANCE_URL', '')).'/lightning/r/Broker__c/'.$record->salesforce_id.'/view'
                                        : null)
                                    ->openUrlInNewTab()
                                    ->visible(fn (?Broker $record): bool => filled($record?->salesforce_id))
                            )
                            ->visible(fn ($record): bool => filled($record?->salesforce_id)),

                        Select::make('user_id')
                            ->label('Usuario')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->unique(ignoreRecord: true),

                        Select::make('broker_category_id')
                            ->label('Categoria')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),

                        TextInput::make('display_name')
                            ->label('Nombre visible')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('contact_email')
                            ->label('Email de contacto')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('contact_phone')
                            ->label('Telefono de contacto')
                            ->maxLength(255),

                        CuratorPicker::make('avatar_image_id')
                            ->label('Avatar'),

                        TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
