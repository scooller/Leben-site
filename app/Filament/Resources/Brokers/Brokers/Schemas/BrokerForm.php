<?php

namespace App\Filament\Resources\Brokers\Brokers\Schemas;

use App\Models\Broker;
use App\Models\SalesforceOpportunity;
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
                                    ->url(fn(?Broker $record): ?string => filled($record?->salesforce_id)
                                        ? (config('services.salesforce.instance_url') ?? env('SF_INSTANCE_URL', '')) . '/lightning/r/Broker__c/' . $record->salesforce_id . '/view'
                                        : null)
                                    ->openUrlInNewTab()
                                    ->visible(fn(?Broker $record): bool => filled($record?->salesforce_id))
                            )
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

                        Select::make('projects_portfolio_readonly')
                            ->label('Portafolio de proyectos (desde oportunidades)')
                            ->options(fn(?Broker $record): array => collect(self::resolveProjectsPortfolio($record))
                                ->mapWithKeys(fn(string $project): array => [$project => $project])
                                ->all())
                            ->multiple()
                            ->dehydrated(false)
                            ->disabled()
                            ->afterStateHydrated(function (Select $component, ?Broker $record): void {
                                $component->state(self::resolveProjectsPortfolio($record));
                            })
                            ->visible(fn(?Broker $record): bool => $record !== null)
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    private static function resolveProjectsPortfolio(?Broker $record): array
    {
        if ($record === null) {
            return [];
        }

        $portfolio = is_array($record->projects_portfolio)
            ? $record->projects_portfolio
            : [];

        $projects = array_values(array_filter(array_map(
            fn($project): string => trim((string) $project),
            $portfolio
        ), fn(string $project): bool => $project !== ''));

        if ($projects !== [] || ! filled($record->salesforce_id)) {
            return $projects;
        }

        return SalesforceOpportunity::query()
            ->where('broker_salesforce_id', (string) $record->salesforce_id)
            ->whereNotNull('proyecto_name')
            ->whereRaw("TRIM(proyecto_name) != ''")
            ->distinct()
            ->orderBy('proyecto_name')
            ->limit(100)
            ->pluck('proyecto_name')
            ->map(fn($project): string => trim((string) $project))
            ->filter(fn(string $project): bool => $project !== '')
            ->values()
            ->all();
    }
}
