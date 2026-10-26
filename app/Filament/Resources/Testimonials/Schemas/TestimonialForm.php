<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Información del Testimonio')
                    ->schema([
                        Select::make('project_id')
                            ->label('Proyecto Asociado (Opcional)')
                            ->relationship('project', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Si se asocia a un proyecto, aparecerá en su ficha y en el Home. Si está vacío, solo en el Home.'),

                        TextInput::make('name')
                            ->label('Nombre del Cliente')
                            ->required(),

                        TextInput::make('role')
                            ->label('Cargo / Ubicación (ej: Propietario en Puerto Varas)')
                            ->nullable(),

                        TextInput::make('rating')
                            ->label('Puntuación (1.0 a 5.0)')
                            ->numeric()
                            ->default(5.0)
                            ->minValue(1.0)
                            ->maxValue(5.0)
                            ->step(0.1)
                            ->required(),

                        TextInput::make('review_date_human')
                            ->label('Fecha descriptiva (ej: hace 17 meses)')
                            ->nullable(),

                        TextInput::make('sort_order')
                            ->label('Orden de Visualización')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_published')
                            ->label('Publicado')
                            ->default(true),

                        Textarea::make('review_text')
                            ->label('Texto de la Reseña / Testimonio')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Avatar / Fotografía')
                    ->schema([
                        CuratorPicker::make('avatar_id')
                            ->label('Avatar (Curator)'),

                        TextInput::make('avatar_url')
                            ->label('URL directa de Avatar (Fallback)')
                            ->nullable(),
                    ])->columns(2),
            ]);
    }
}
