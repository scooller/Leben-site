<?php

namespace App\Filament\Resources\Asesores\Schemas;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AsesorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del Asesor')
                    ->schema([
                        TextInput::make('salesforce_id')
                            ->label('Salesforce ID')
                            ->maxLength(30)
                            ->unique(ignoreRecord: true)
                            ->readOnly(true),

                        TextInput::make('first_name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('last_name')
                            ->label('Apellido')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('whatsapp_owner')
                            ->label('WhatsApp')
                            ->maxLength(255),

                        CuratorPicker::make('avatar_image_id')
                            ->label('Avatar Manual (Curator)')
                            ->helperText('Si cargas una imagen aquí, tendrá prioridad sobre el avatar sincronizado desde Salesforce.'),

                        TextInput::make('avatar_url')
                            ->label('Avatar URL (Salesforce)')
                            ->url()
                            ->maxLength(2048)
                            ->helperText('Este valor se sincroniza desde MediumPhotoUrl y se usa como fallback cuando no hay avatar manual en Curator.'),

                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
