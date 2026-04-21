<?php

namespace App\Filament\Exports;

use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class ContactSubmissionExporter extends Exporter
{
    protected static ?string $model = ContactSubmission::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('rut')
                ->label('RUT'),
            ...self::getDynamicColumns(),
            ExportColumn::make('salesforce_case_id')
                ->label('Salesforce Lead ID'),
            ExportColumn::make('salesforce_case_error')
                ->label('Salesforce Error'),
            ExportColumn::make('recipient_email')
                ->label('Destinatario'),
            ExportColumn::make('ip_address')
                ->label('IP'),
            ExportColumn::make('user_agent')
                ->label('User Agent'),
            ExportColumn::make('submitted_at')
                ->label('Enviado en'),
            ExportColumn::make('created_at')
                ->label('Creado en'),
            ExportColumn::make('updated_at')
                ->label('Actualizado en'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your contact submission export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }

    /**
     * @return array<int, ExportColumn>
     */
    private static function getDynamicColumns(): array
    {
        $dynamicColumns = collect(self::configuredExportFields())
            ->map(function (array $field): ExportColumn {
                $key = (string) $field['key'];

                return ExportColumn::make("field_{$key}")
                    ->label(self::resolveFieldLabel($key, $field))
                    ->state(fn (ContactSubmission $record): string => self::formatDynamicValue($record->fields[$key] ?? null, $field));
            })
            ->values()
            ->all();

        $dynamicColumns[] = ExportColumn::make('contact_comuna')
            ->label('Comuna')
            ->state(fn (ContactSubmission $record): string => self::resolveCanonicalFieldValue($record->fields, [
                'comuna',
                'commune',
                'district',
                'project_commune',
            ]));

        $dynamicColumns[] = ExportColumn::make('contact_proyecto')
            ->label('Proyecto')
            ->state(fn (ContactSubmission $record): string => self::resolveCanonicalFieldValue($record->fields, [
                'proyecto',
                'project',
                'project_name',
                'nombre_proyecto',
            ]));

        return $dynamicColumns;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function configuredExportFields(): array
    {
        return collect(SiteSetting::current()->contact_form_fields ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
            ->reject(function (array $field): bool {
                $key = (string) ($field['key'] ?? '');

                return in_array($key, [
                    'name',
                    'email',
                    'phone',
                    'rut',
                    'comuna',
                    'commune',
                    'district',
                    'project_commune',
                    'proyecto',
                    'project',
                    'project_name',
                    'nombre_proyecto',
                ], true);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function formatDynamicFields(mixed $fields): array
    {
        if (! is_array($fields) || $fields === []) {
            return [];
        }

        $definitions = self::fieldDefinitions();
        $formatted = [];

        foreach ($fields as $key => $value) {
            $fieldKey = (string) $key;
            $definition = $definitions[$fieldKey] ?? ['key' => $fieldKey];
            $label = self::resolveFieldLabel($fieldKey, $definition);

            if (array_key_exists($label, $formatted)) {
                $label = sprintf('%s (%s)', $label, $fieldKey);
            }

            $formatted[$label] = self::formatDynamicValue($value, $definition);
        }

        return $formatted;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function fieldDefinitions(): array
    {
        $configuredFields = collect(SiteSetting::current()->contact_form_fields ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
            ->mapWithKeys(fn (array $field): array => [((string) $field['key']) => $field]);

        return $configuredFields
            ->union(self::supplementalFieldDefinitions())
            ->all();
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function supplementalFieldDefinitions(): array
    {
        return [
            'phone' => ['key' => 'phone', 'label' => 'Telefono', 'type' => 'text'],
            'telefono' => ['key' => 'telefono', 'label' => 'Telefono', 'type' => 'text'],
            'fono' => ['key' => 'fono', 'label' => 'Telefono', 'type' => 'text'],
            'celular' => ['key' => 'celular', 'label' => 'Celular', 'type' => 'text'],
            'whatsapp' => ['key' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'text'],
            'comuna' => ['key' => 'comuna', 'label' => 'Comuna', 'type' => 'text'],
            'commune' => ['key' => 'commune', 'label' => 'Comuna', 'type' => 'text'],
            'district' => ['key' => 'district', 'label' => 'Comuna', 'type' => 'text'],
            'project_commune' => ['key' => 'project_commune', 'label' => 'Comuna del proyecto', 'type' => 'text'],
            'proyecto' => ['key' => 'proyecto', 'label' => 'Proyecto', 'type' => 'text'],
            'project' => ['key' => 'project', 'label' => 'Proyecto', 'type' => 'text'],
            'project_name' => ['key' => 'project_name', 'label' => 'Proyecto', 'type' => 'text'],
            'nombre_proyecto' => ['key' => 'nombre_proyecto', 'label' => 'Proyecto', 'type' => 'text'],
            'message' => ['key' => 'message', 'label' => 'Mensaje', 'type' => 'textarea'],
            'mensaje' => ['key' => 'mensaje', 'label' => 'Mensaje', 'type' => 'textarea'],
            'utm_source' => ['key' => 'utm_source', 'label' => 'UTM Source', 'type' => 'text'],
            'utm_medium' => ['key' => 'utm_medium', 'label' => 'UTM Medium', 'type' => 'text'],
            'utm_campaign' => ['key' => 'utm_campaign', 'label' => 'UTM Campaign', 'type' => 'text'],
            'utm_term' => ['key' => 'utm_term', 'label' => 'UTM Term', 'type' => 'text'],
            'utm_content' => ['key' => 'utm_content', 'label' => 'UTM Content', 'type' => 'text'],
            'utm_site' => ['key' => 'utm_site', 'label' => 'UTM Site', 'type' => 'text'],
        ];
    }

    /**
     * @param  array<int, string>  $aliases
     */
    private static function resolveCanonicalFieldValue(mixed $fields, array $aliases): string
    {
        if (! is_array($fields)) {
            return '-';
        }

        $definitions = self::fieldDefinitions();

        foreach ($aliases as $alias) {
            if (! array_key_exists($alias, $fields)) {
                continue;
            }

            $formattedValue = self::formatDynamicValue($fields[$alias], $definitions[$alias] ?? ['key' => $alias]);

            if ($formattedValue !== '-') {
                return $formattedValue;
            }
        }

        return '-';
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function resolveFieldLabel(string $key, array $definition): string
    {
        if (filled($definition['label'] ?? null)) {
            return (string) $definition['label'];
        }

        return Str::headline($key);
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
            return $value ? 'Si' : 'No';
        }

        if (is_scalar($value)) {
            $stringValue = trim((string) $value);

            return $stringValue === '' ? '-' : $stringValue;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '[valor no serializable]';
    }
}
