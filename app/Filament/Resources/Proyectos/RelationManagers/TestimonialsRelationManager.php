<?php

namespace App\Filament\Resources\Proyectos\RelationManagers;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TestimonialsRelationManager extends RelationManager
{
    protected static string $relationship = 'testimonials';

    protected static ?string $title = 'Testimonios del Proyecto';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Nombre del Cliente')
                    ->required(),

                TextInput::make('role')
                    ->label('Cargo / Ubicación')
                    ->nullable(),

                TextInput::make('rating')
                    ->label('Puntuación')
                    ->numeric()
                    ->default(5.0)
                    ->minValue(1.0)
                    ->maxValue(5.0)
                    ->step(0.1)
                    ->required(),

                TextInput::make('review_date_human')
                    ->label('Fecha descriptiva')
                    ->nullable(),

                CuratorPicker::make('avatar_id')
                    ->label('Avatar (Curator)'),

                TextInput::make('avatar_url')
                    ->label('URL directa de Avatar (Fallback)')
                    ->nullable(),

                TextInput::make('sort_order')
                    ->label('Orden')
                    ->numeric()
                    ->default(0),

                Toggle::make('is_published')
                    ->label('Publicado')
                    ->default(true),

                Textarea::make('review_text')
                    ->label('Reseña')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('resolved_avatar_url')
                    ->label('Avatar')
                    ->circular(),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('role')
                    ->label('Cargo / Ubicación')
                    ->sortable(),

                TextColumn::make('rating')
                    ->label('Rating')
                    ->badge()
                    ->color('amber')
                    ->formatStateUsing(fn ($state): string => "★ {$state}")
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),

                ToggleColumn::make('is_published')
                    ->label('Publicado')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order', 'asc');
    }
}
