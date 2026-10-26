<?php

namespace App\Filament\Resources\Menus\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Configuración del Menú')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del Menú')
                            ->required(),

                        Select::make('location')
                            ->label('Ubicación en el Sitio')
                            ->options([
                                'header_main' => 'Cabecera Principal (header_main)',
                                'header_dropdown' => 'Dropdown Agrupado (header_dropdown)',
                                'footer_corporate' => 'Pie de Página Corporativo (footer_corporate)',
                                'footer_legal' => 'Pie de Página Legal (footer_legal)',
                            ])
                            ->required()
                            ->unique(ignoreRecord: true),
                    ])->columns(2),

                Section::make('Elementos del Menú (Items)')
                    ->schema([
                        Repeater::make('allItems')
                            ->label('Items de Navegación')
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Nuevo Enlace')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Texto / Etiqueta')
                                    ->required(),

                                TextInput::make('url')
                                    ->label('URL / Ruta (ej: /invest o https://...)')
                                    ->required(),

                                Select::make('target')
                                    ->label('Destino (target)')
                                    ->options([
                                        '_self' => 'Misma Pestaña (_self)',
                                        '_blank' => 'Nueva Pestaña (_blank)',
                                    ])
                                    ->default('_self')
                                    ->required(),

                                TextInput::make('badge')
                                    ->label('Badge / Etiqueta')
                                    ->nullable(),

                                Toggle::make('is_featured')
                                    ->label('Botón Destacado')
                                    ->default(false),

                                Toggle::make('is_active')
                                    ->label('Activo')
                                    ->default(true),
                            ])->columns(3),
                    ]),
            ]);
    }
}
