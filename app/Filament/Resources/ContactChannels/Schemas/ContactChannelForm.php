<?php

namespace App\Filament\Resources\ContactChannels\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactChannelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('slug')
                        ->label('Slug (identificador único)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->maxLength(100)
                        ->helperText('Minúsculas, guiones y números. Ej: sale, argomedo, capitanes'),
                    TextInput::make('name')
                        ->label('Nombre visible')
                        ->required()
                        ->maxLength(255),
                ]),

            Section::make('Estado')
                ->columns(2)
                ->schema([
                    Toggle::make('is_active')
                        ->label('Activo')
                        ->default(true),
                    Toggle::make('is_default')
                        ->label('Canal por defecto')
                        ->helperText('Solo un canal puede ser el predeterminado. Los envíos sin canal asignado irán aquí.'),
                ]),

            Section::make('Notificaciones')
                ->schema([
                    TextInput::make('notification_email')
                        ->label('Email de notificación')
                        ->email()
                        ->nullable()
                        ->maxLength(255)
                        ->helperText('Si está vacío, se usará el email global configurado en Ajustes del sitio.'),
                ]),

            Section::make('Dominios asociados')
                ->description('Lista de dominios que se asocian automáticamente a este canal. Soporta comodines: *.sale.cl')
                ->schema([
                    Repeater::make('domain_patterns')
                        ->label('Patrones de dominio')
                        ->simple(
                            TextInput::make('value')
                                ->label('Dominio')
                                ->placeholder('sale.ileben.cl o *.sale.cl')
                                ->required()
                                ->maxLength(255),
                        )
                        ->addActionLabel('Agregar dominio')
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0),
                ]),

            Section::make('Configuración de formulario')
                ->description('Si está vacío, se usará la configuración global del formulario de contacto (Ajustes del sitio). Define aquí los campos específicos de este canal.')
                ->schema([
                    KeyValue::make('form_fields')
                        ->label('Campos del formulario (JSON)')
                        ->keyLabel('Clave')
                        ->valueLabel('Valor JSON')
                        ->helperText('Avanzado: edita directamente el JSON de campos de formulario para este canal.')
                        ->nullable()
                        ->columnSpanFull(),
                ])
                ->collapsed(),
        ]);
    }
}
