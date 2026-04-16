<?php

namespace App\Filament\Resources\ContactSubmissions\ContactSubmissions\Tables;

use App\Filament\Exports\ContactSubmissionExporter;
use App\Models\SiteSetting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

class ContactSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Exportar Contactos')
                    ->icon('heroicon-o-document-arrow-up')
                    ->exporter(ContactSubmissionExporter::class),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int, TextColumn>
     */
    private static function columns(): array
    {
        $dynamicColumns = collect(self::fieldDefinitions())
            ->map(function (array $field): TextColumn {
                $key = (string) $field['key'];
                $label = filled($field['label'] ?? null)
                    ? (string) $field['label']
                    : Str::headline($key);

                return TextColumn::make("fields.{$key}")
                    ->label($label)
                    ->state(fn ($record): string => self::formatDynamicValue($record->fields[$key] ?? null, $field))
                    ->placeholder('-')
                    ->wrap()
                    ->limit(60)
                    ->toggleable();
            })
            ->values()
            ->all();

        if ($dynamicColumns === []) {
            $dynamicColumns = [
                TextColumn::make('fields_summary')
                    ->label('Campos')
                    ->state(fn ($record): string => self::summarizeDynamicFields($record->fields))
                    ->placeholder('-')
                    ->wrap()
                    ->toggleable(),
            ];
        }

        return [
            TextColumn::make('id')
                ->label('#')
                ->sortable(),
            ...$dynamicColumns,
            TextColumn::make('submitted_at')
                ->label('Enviado')
                ->dateTime()
                ->sortable(),
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fieldDefinitions(): array
    {
        return collect(SiteSetting::current()->contact_form_fields ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
            ->mapWithKeys(fn (array $field): array => [((string) $field['key']) => $field])
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
}
