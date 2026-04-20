<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Plant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pago')
                    ->columns(2)
                    ->components([
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('project_id')
                            ->label('Proyecto')
                            ->relationship('project', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('plant_id', null)),
                        Select::make('plant_id')
                            ->label('Planta')
                            ->relationship(
                                name: 'plant',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query, Get $get) => $query
                                    ->when(
                                        filled($get('project_id')),
                                        fn ($filteredQuery) => $filteredQuery->whereHas(
                                            'proyecto',
                                            fn ($projectQuery) => $projectQuery->whereKey($get('project_id')),
                                        ),
                                    )
                                    ->with('proyecto')
                                    ->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Plant $record): string => $record->proyecto?->name
                                ? $record->name.' - '.$record->proyecto->name
                                : (string) $record->name)
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get): bool => blank($get('project_id')))
                            ->helperText('Selecciona primero un proyecto para filtrar sus plantas.')
                            ->live(),
                        Select::make('gateway')
                            ->options(PaymentGateway::toSelectArray())
                            ->searchable()
                            ->required(),
                        TextInput::make('gateway_tx_id'),
                        TextInput::make('amount')
                            ->required()
                            ->numeric(),
                        TextInput::make('currency')
                            ->required()
                            ->default('CLP'),
                        Select::make('status')
                            ->options(PaymentStatus::toSelectArray())
                            ->searchable()
                            ->required()
                            ->default(PaymentStatus::PENDING->value),
                        DateTimePicker::make('completed_at'),
                    ]),
                Section::make('Datos de Facturacion')
                    ->columns(2)
                    ->components([
                        TextInput::make('billing_name')
                            ->label('Nombre Facturacion')
                            ->maxLength(255),
                        TextInput::make('billing_email')
                            ->label('Email Facturacion')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('billing_phone')
                            ->label('Telefono Facturacion')
                            ->maxLength(20),
                        TextInput::make('billing_rut')
                            ->label('RUT Facturacion')
                            ->maxLength(12),
                    ]),
                Section::make('Metadata')
                    ->components([
                        Textarea::make('metadata')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
