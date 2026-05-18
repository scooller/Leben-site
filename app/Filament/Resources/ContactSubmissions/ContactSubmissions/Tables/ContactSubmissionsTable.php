<?php

namespace App\Filament\Resources\ContactSubmissions\ContactSubmissions\Tables;

use App\Filament\Exports\ContactSubmissionExporter;
use App\Jobs\CreateSalesforceCaseJob;
use App\Models\ContactChannel;
use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

class ContactSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with(['channel:id,name,slug_badge_color']))
            ->columns(self::columns())
            ->defaultSort('submitted_at', 'desc')
            ->searchable()
            ->searchPlaceholder('Buscar por RUT o email...')
            ->filters([
                SelectFilter::make('contact_channel_id')
                    ->label('Canal')
                    ->options(
                        fn(): array => ContactChannel::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                    )
                    ->placeholder('Todos los canales')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->modalHeading('¿Eliminar contacto?')
                    ->modalDescription('Esta acción es irreversible. Se eliminará permanentemente el envío del contacto y no podrá recuperarse.')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->color('danger'),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Exportar Contactos')
                    ->icon('heroicon-o-document-arrow-up')
                    ->exporter(ContactSubmissionExporter::class),
                BulkActionGroup::make([
                    BulkAction::make('syncSelectedToSalesforce')
                        ->label('Sincronizar con Salesforce')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('¿Sincronizar contactos seleccionados con Salesforce?')
                        ->modalDescription('Se intentará crear o actualizar el Lead en Salesforce para cada contacto seleccionado. Los errores previos serán limpiados antes del reintento.')
                        ->modalSubmitActionLabel('Sí, sincronizar')
                        ->action(function (Collection $records): void {
                            $leadEnabled = (bool) config('services.salesforce.lead_enabled', config('services.salesforce.case_enabled', false));

                            if (! $leadEnabled) {
                                Notification::make()
                                    ->title('Salesforce deshabilitado')
                                    ->body('La sincronización con Salesforce está deshabilitada en la configuración.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $synced = 0;
                            $failed = 0;

                            $records->each(function (ContactSubmission $record) use (&$synced, &$failed): void {
                                $record->update(['salesforce_case_error' => null]);
                                CreateSalesforceCaseJob::dispatchSync($record, 'manual');
                                $record->refresh();

                                if (filled($record->salesforce_case_id)) {
                                    $synced++;
                                } else {
                                    $failed++;
                                }
                            });

                            if ($failed === 0) {
                                Notification::make()
                                    ->title('Sincronización completada')
                                    ->body("Se sincronizaron {$synced} contacto(s) con Salesforce.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Sincronización parcial')
                                    ->body("Sincronizados: {$synced}. Con error: {$failed}.")
                                    ->warning()
                                    ->send();
                            }
                        }),
                    DeleteBulkAction::make()
                        ->modalHeading('¿Eliminar contactos seleccionados?')
                        ->modalDescription('Esta acción es irreversible. Se eliminarán permanentemente todos los envíos seleccionados y no podrán recuperarse.')
                        ->modalSubmitActionLabel('Sí, eliminar todos')
                        ->modalIcon('heroicon-o-exclamation-triangle'),
                ]),
            ]);
    }

    /**
     * @return array<int, TextColumn>
     */
    private static function columns(): array
    {
        $dynamicColumns = collect(self::fieldDefinitions())
            ->filter(function (array $field): bool {
                $key = (string) $field['key'];
                // Omitir las columnas de rango de renta, se agregan manualmente
                return !in_array($key, ['rango_renta', 'rango de renta', 'rango_de_renta', 'income_range', 'renta_liquida']);
            })
            ->map(function (array $field): TextColumn {
                $key = (string) $field['key'];
                $label = filled($field['label'] ?? null)
                    ? (string) $field['label']
                    : Str::headline($key);

                // Si el label es exactamente 'Rango de renta', la columna se oculta del selector
                $column = TextColumn::make("fields.{$key}")
                    ->label($label)
                    ->state(fn($record): string => self::formatDynamicValue(self::resolveDynamicFieldValue($record->fields, $key), $field))
                    ->placeholder('-')
                    ->wrap()
                    ->limit(60)
                    ->sortable();

                if (Str::lower($label) === 'rango de renta') {
                    $column = $column->toggleable(isToggledHiddenByDefault: true);
                } else {
                    $column = $column->toggleable();
                }

                return $column;
            })
            ->values()
            ->all();

        // Columna robusta para Rango de renta (siempre visible)
        $rangoRentaColumn = TextColumn::make('rango_renta')
            ->label('Rango de renta')
            ->state(function ($record) {
                $fields = $record->fields ?? [];
                $aliases = [
                    'rango_renta',
                    'rango de renta',
                    'rango_de_renta',
                    'en_que_rango_se_encuentra_tu_renta_liquida',
                    'income_range',
                    'renta_liquida',
                    'renta',
                    'rango',
                    'renta_aprox',
                    'renta_aproximada'
                ];
                foreach ($aliases as $alias) {
                    foreach ($fields as $key => $value) {
                        if (stripos($key, $alias) !== false && !empty($value)) {
                            return $value;
                        }
                    }
                }
                // fallback: buscar por normalización
                foreach ($fields as $key => $value) {
                    $normalized = Str::of($key)
                        ->ascii()
                        ->lower()
                        ->replaceMatches('/[^a-z0-9]+/', '_')
                        ->trim('_')
                        ->toString();
                    if (in_array($normalized, $aliases) && !empty($value)) {
                        return $value;
                    }
                }
                return '-';
            })
            ->placeholder('-')
            ->wrap()
            ->limit(60)
            ->sortable()
            ->toggleable();

        if ($dynamicColumns === []) {
            $dynamicColumns = [
                TextColumn::make('fields_summary')
                    ->label('Campos')
                    ->state(fn($record): string => self::summarizeDynamicFields($record->fields))
                    ->placeholder('-')
                    ->wrap()
                    ->toggleable(),
            ];
        }

        return [
            TextColumn::make('id')
                ->label('#')
                ->sortable(),
            TextColumn::make('channel.name')
                ->label('Canal')
                ->placeholder('Sin canal')
                ->badge()
                ->color(fn($record): array => self::resolveBadgeColor($record->channel?->slug_badge_color))
                ->sortable()
                ->toggleable(),
            // TextColumn::make('rut')
            //     ->label('RUT')
            //     ->placeholder('-')
            //     ->searchable()
            //     ->toggleable(isToggledHiddenByDefault: true),
            $rangoRentaColumn,
            ...$dynamicColumns,
            TextColumn::make('submitted_at')
                ->label('Enviado')
                ->dateTime()
                ->sortable(),
            // sincronizado con salesforce
            TextColumn::make('salesforce_synced_at')
                ->label('Sincronizado con Salesforce')
                ->state(fn($record) => $record->salesforceSyncedAt())
                ->dateTime()
                ->placeholder('No disponible')
                ->sortable(),
            IconColumn::make('salesforce_synced')
                ->label('Salesforce')
                ->state(fn($record): bool => filled($record->salesforce_case_id))
                ->boolean()
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger')
                ->tooltip(fn($record): string => filled($record->salesforce_case_id)
                    ? 'Lead ID: ' . $record->salesforce_case_id
                    : (filled($record->salesforce_case_error) ? 'Error: ' . $record->salesforce_case_error : 'No sincronizado'))
                ->toggleable(),
        ];
    }

    private static function summarizeDynamicFields(mixed $fields): string
    {
        if (! is_array($fields) || $fields === []) {
            return '-';
        }

        $items = [];

        foreach ($fields as $key => $value) {
            $items[] = sprintf('%s: %s', Str::headline((string) $key), self::formatDynamicValue($value, []));
        }

        return implode(' | ', $items);
    }

    private static function resolveDynamicFieldValue(mixed $fields, string $fieldKey): mixed
    {
        if (! is_array($fields) || $fields === []) {
            return null;
        }

        $normalizedFieldMap = [];

        foreach ($fields as $key => $value) {
            $normalizedKey = Str::of((string) $key)
                ->ascii()
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->toString();

            if ($normalizedKey === '' || array_key_exists($normalizedKey, $normalizedFieldMap)) {
                continue;
            }

            $normalizedFieldMap[$normalizedKey] = $value;
        }

        foreach (self::fieldLookupKeys($fieldKey) as $lookupKey) {
            if (! array_key_exists($lookupKey, $fields)) {
                $normalizedLookupKey = Str::of($lookupKey)
                    ->ascii()
                    ->lower()
                    ->replaceMatches('/[^a-z0-9]+/', '_')
                    ->trim('_')
                    ->toString();

                if ($normalizedLookupKey === '' || ! array_key_exists($normalizedLookupKey, $normalizedFieldMap)) {
                    continue;
                }

                $value = $normalizedFieldMap[$normalizedLookupKey];

                if (is_string($value) && trim($value) === '') {
                    continue;
                }

                return $value;
            }

            $value = $fields[$lookupKey];

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function fieldLookupKeys(string $fieldKey): array
    {
        $normalized = Str::of($fieldKey)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        $aliases = [
            $fieldKey,
            $normalized,
        ];

        $byAlias = [
            'rango_renta' => [
                'rango_de_renta',
                'en_que_rango_se_encuentra_tu_renta_liquida',
                'income_range',
                'renta_liquida',
            ],
            'rango_de_renta' => [
                'rango_renta',
                'en_que_rango_se_encuentra_tu_renta_liquida',
                'income_range',
                'renta_liquida',
            ],
            'income_range' => [
                'rango_renta',
                'rango_de_renta',
                'en_que_rango_se_encuentra_tu_renta_liquida',
            ],
            'codeudor' => [
                'tienes_la_posibilidad_de_complementar_tu_renta',
            ],
            'origen_prospecto' => [
                'origen_del_prospecto',
            ],
            'origen_del_prospecto' => [
                'origen_prospecto',
            ],
        ];

        foreach ($byAlias[$normalized] ?? [] as $alias) {
            $aliases[] = $alias;
        }

        return array_values(array_unique(array_filter($aliases, static fn(string $key): bool => $key !== '')));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fieldDefinitions(): array
    {
        return collect(SiteSetting::current()->contact_form_fields ?? [])
            ->filter(fn(mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
            ->mapWithKeys(fn(array $field): array => [((string) $field['key']) => $field])
            ->union([
                'comuna' => [
                    'key' => 'comuna',
                    'label' => 'Comuna',
                    'type' => 'text',
                ],
                'proyecto' => [
                    'key' => 'proyecto',
                    'label' => 'Proyecto',
                    'type' => 'text',
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function formatDynamicValue(mixed $value, array $definition): string
    {
        if (($definition['type'] ?? null) === 'select' && is_scalar($value)) {
            foreach (($definition['options'] ?? []) as $option) {
                if (! is_array($option)) {
                    continue;
                }

                if ((string) ($option['value'] ?? '') === (string) $value) {
                    return (string) ($option['label'] ?? $value);
                }
            }
        }

        if ($value === null) {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if (is_scalar($value)) {
            $stringValue = trim((string) $value);

            return $stringValue === '' ? '-' : $stringValue;
        }

        $encoded = Js::encode($value);

        return is_string($encoded) ? $encoded : '[valor no serializable]';
    }

    /**
     * @return array<string, string>
     */
    private static function resolveBadgeColor(?string $color): array
    {
        return match (strtolower(trim((string) $color))) {
            'success', 'green', 'emerald' => Color::Emerald,
            'warning', 'yellow', 'amber' => Color::Amber,
            'danger', 'red', 'rose' => Color::Red,
            'info', 'blue', 'sky' => Color::Blue,
            'primary' => Color::Indigo,
            default => Color::Gray,
        };
    }
}
