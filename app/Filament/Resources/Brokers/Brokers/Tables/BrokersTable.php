<?php

namespace App\Filament\Resources\Brokers\Brokers\Tables;

use App\Models\SalesforceOpportunity;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BrokersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('opportunities_total', 'desc')
            ->columns([
                ImageColumn::make('avatar_image_id')
                    ->label('Avatar')
                    ->getStateUsing(fn($record): ?string => $record->avatarImageMedia?->url)
                    ->circular(),

                TextColumn::make('salesforce_id')
                    ->label('SF ID')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('display_name')
                    ->label('Nombre')
                    ->state(fn($record): string => $record->resolved_name)
                    ->searchable(['display_name', 'contact_email'])
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Categoria')
                    ->badge(),

                TextColumn::make('resolved_phone')
                    ->label('Telefono')
                    ->toggleable(),

                TextColumn::make('contact_email')
                    ->label('Email')
                    ->state(fn($record): ?string => $record->resolved_email)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('opportunities_total')
                    ->label('Opp total')
                    ->sortable(),

                TextColumn::make('opportunities_open')
                    ->label('Opp abiertas')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('opportunities_won')
                    ->label('Opp ganadas')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('closure_rate_30d')
                    ->label('Cierre 30d')
                    ->formatStateUsing(fn($state): string => $state === null ? '-' : number_format((float) $state, 2) . '%')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('pipeline_amount_30d')
                    ->label('Pipeline 30d')
                    ->money('CLP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('won_amount_30d')
                    ->label('Ganado 30d')
                    ->money('CLP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_opportunity_at')
                    ->label('Ultima opp')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('projects_portfolio')
                    ->label('Proyectos')
                    ->formatStateUsing(function ($state, $record): string {
                        $projects = self::resolveProjectsPortfolio($state, $record);

                        if ($projects === []) {
                            return '-';
                        }

                        $visible = array_slice($projects, 0, 2);
                        $hiddenCount = max(0, count($projects) - count($visible));
                        $badges = array_map(static function (string $project): string {
                            return '<span class="inline-flex items-center rounded-md border border-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700">'
                                . e($project)
                                . '</span>';
                        }, $visible);

                        if ($hiddenCount > 0) {
                            $badges[] = '<span class="inline-flex items-center rounded-md border border-gray-200 px-2 py-0.5 text-xs font-medium text-gray-500">+'
                                . $hiddenCount
                                . '</span>';
                        }

                        return '<div class="flex flex-wrap gap-1">' . implode('', $badges) . '</div>';
                    })
                    ->html()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('broker_category_id')
                    ->label('Categoria')
                    ->relationship('category', 'name'),
                SelectFilter::make('is_active')
                    ->label('Estado')
                    ->options([
                        1 => 'Activo',
                        0 => 'Inactivo',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function resolveProjectsPortfolio(mixed $state, mixed $record): array
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = is_array($decoded) ? $decoded : [];
        }

        $normalized = array_values(array_filter(array_map(
            fn($project): string => trim((string) $project),
            is_array($state) ? $state : []
        ), fn(string $project): bool => $project !== ''));

        if ($normalized === [] && filled($record?->salesforce_id)) {
            $normalized = SalesforceOpportunity::query()
                ->where('broker_salesforce_id', (string) $record->salesforce_id)
                ->whereNotNull('proyecto_name')
                ->whereRaw("TRIM(proyecto_name) != ''")
                ->distinct()
                ->orderBy('proyecto_name')
                ->limit(50)
                ->pluck('proyecto_name')
                ->map(fn($project): string => trim((string) $project))
                ->filter(fn(string $project): bool => $project !== '')
                ->values()
                ->all();
        }

        return $normalized;
    }
}
