<?php

namespace App\Filament\Resources\Brokers\Brokers\Schemas;

use Awcodes\Curator\Components\Forms\CuratorPicker;
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
                            ->visible(fn($record): bool => filled($record?->salesforce_id)),

                        TextInput::make('salesforce_link')
                            ->label('Ver en Salesforce')
                            ->disabled()
                            ->formatStateUsing(function ($record): string {
                                if ($record === null || blank($record->salesforce_id)) {
                                    return '';
                                }

                                $sfId = (string) $record->salesforce_id;
                                $instanceUrl = config('services.salesforce.instance_url') ?? env('SF_INSTANCE_URL', '');

                                if (blank($instanceUrl)) {
                                    return 'Instancia Salesforce no configurada';
                                }

                                $profileUrl = rtrim((string) $instanceUrl, '/') . '/lightning/r/Broker__c/' . $sfId . '/view';

                                return $profileUrl;
                            })
                            ->visible(fn($record): bool => filled($record?->salesforce_id)),

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
