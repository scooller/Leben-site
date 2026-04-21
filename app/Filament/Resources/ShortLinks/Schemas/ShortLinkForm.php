<?php

namespace App\Filament\Resources\ShortLinks\Schemas;

use App\Enums\ShortLinkStatus;
use App\Models\SiteSetting;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Support\Str;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShortLinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Link corto')
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->label('Titulo')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->minLength(2)
                            ->maxLength(32)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->default(fn (): string => Str::lower(Str::random(2)))
                            ->helperText('Se usa en la URL corta /s/{slug}.'),
                        Select::make('status')
                            ->label('Estado')
                            ->options(ShortLinkStatus::toSelectArray())
                            ->searchable()
                            ->required()
                            ->default(ShortLinkStatus::ACTIVE->value),
                        TextInput::make('destination_url')
                            ->label('URL destino')
                            ->url()
                            ->required()
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        TextInput::make('tag_manager_id')
                            ->label('GTM ID (override)')
                            ->placeholder('GTM-XXXXXXX')
                            ->maxLength(50)
                            ->regex('/^GTM-[A-Z0-9]+$/')
                            ->default(fn (): ?string => SiteSetting::get('tag_manager_id') ?: null)
                            ->helperText('Si se deja vacio, usa el tag_manager_id global de Site Settings.'),
                        DateTimePicker::make('expires_at')
                            ->label('Expira en')
                            ->seconds(false),
                        KeyValue::make('metadata')
                            ->label('Metadata')
                            ->keyLabel('Clave')
                            ->valueLabel('Valor')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
