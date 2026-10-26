<?php

namespace App\Filament\Resources\ContentPages\Schemas;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ContentPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('PageTabs')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Identificación de la Página')
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Título de la Página')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (string $operation, $state, callable $set) {
                                                if ($operation === 'create') {
                                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                                }
                                            }),

                                        TextInput::make('slug')
                                            ->label('Slug / Identificador URL')
                                            ->required()
                                            ->unique(ignoreRecord: true),

                                        TextInput::make('subtitle')
                                            ->label('Subtítulo')
                                            ->nullable(),

                                        TextInput::make('badge')
                                            ->label('Badge / Etiqueta Superior')
                                            ->nullable(),

                                        Toggle::make('is_published')
                                            ->label('Publicada en Web')
                                            ->default(true),
                                    ])->columns(2),

                                Section::make('Banner Hero')
                                    ->schema([
                                        CuratorPicker::make('hero_image_id')
                                            ->label('Imagen Hero (Curator)'),

                                        TextInput::make('hero_image_url')
                                            ->label('URL directa de Imagen Hero (Fallback)')
                                            ->url()
                                            ->nullable(),

                                        TextInput::make('hero_video_url')
                                            ->label('URL Video Hero (MP4 / Stream)')
                                            ->nullable(),
                                    ])->columns(2),
                            ]),

                        Tab::make('Secciones')
                            ->icon('heroicon-o-rectangle-stack')
                            ->schema([
                                Repeater::make('sections')
                                    ->relationship()
                                    ->orderColumn('sort_order')
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => ($state['title'] ?? null) ?: ($state['section_key'] ?? 'Nueva Sección'))
                                    ->schema([
                                        TextInput::make('section_key')
                                            ->label('Clave de Sección (key)')
                                            ->required()
                                            ->helperText('ej: diferenciadores, faq, project_of_month, tiers, banner_cta'),

                                        Select::make('type')
                                            ->label('Tipo de Componente')
                                            ->options([
                                                'features_list' => 'Lista de Características / Sellos',
                                                'cards_grid' => 'Grilla de Tarjetas',
                                                'faq_accordion' => 'Acordeón de Preguntas Frecuentes',
                                                'banner_cta' => 'Banner de Llamado a la Acción',
                                                'custom' => 'Contenido Personalizado / Libre',
                                            ])
                                            ->default('custom')
                                            ->required(),

                                        TextInput::make('title')
                                            ->label('Título de la Sección')
                                            ->nullable(),

                                        TextInput::make('subtitle')
                                            ->label('Subtítulo')
                                            ->nullable(),

                                        TextInput::make('eyebrow')
                                            ->label('Eyebrow / Badge')
                                            ->nullable(),

                                        Toggle::make('is_active')
                                            ->label('Activa')
                                            ->default(true),

                                        KeyValue::make('data')
                                            ->label('Datos Estructurados (Data JSON / Clave-Valor)')
                                            ->reorderable(),
                                    ])->columns(2),
                            ]),

                        Tab::make('SEO & Metadatos')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Section::make('Posicionamiento en Buscadores')
                                    ->schema([
                                        TextInput::make('seo_title')
                                            ->label('Título SEO (Meta Title)')
                                            ->nullable(),

                                        Textarea::make('seo_description')
                                            ->label('Descripción SEO (Meta Description)')
                                            ->rows(3)
                                            ->nullable(),

                                        CuratorPicker::make('seo_og_image_id')
                                            ->label('Imagen Social OG (Curator)'),

                                        TextInput::make('seo_og_image_url')
                                            ->label('URL directa de Imagen OG (Fallback)')
                                            ->nullable(),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
