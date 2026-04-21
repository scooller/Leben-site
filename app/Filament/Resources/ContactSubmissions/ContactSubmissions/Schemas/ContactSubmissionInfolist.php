<?php

namespace App\Filament\Resources\ContactSubmissions\ContactSubmissions\Schemas;

use App\Models\SiteSetting;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

class ContactSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen del envío')
                    ->columns(2)
                    ->components([
                        TextEntry::make('submitted_at')
                            ->label('Fecha de envío')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('recipient_email')
                            ->label('Email destino')
                            ->placeholder('-'),
                        TextEntry::make('ip_address')
                            ->label('IP')
                            ->placeholder('-'),
                        TextEntry::make('user_agent')
                            ->label('Navegador')
                            ->placeholder('-')
                            ->wrap()
                            ->columnSpanFull(),
                    ]),

                Section::make('Campos enviados')
                    ->components([
                        KeyValueEntry::make('fields')
                            ->label('Campos dinámicos')
                            ->state(fn ($record): array => self::formatDynamicFields($record->fields))
                            ->placeholder('Sin datos enviados')
                            ->columnSpanFull(),
                    ]),

                Section::make('Salesforce')
                    ->columns(2)
                    ->components([
                        TextEntry::make('salesforce_case_id')
                            ->label('ID Lead Salesforce')
                            ->placeholder('No sincronizado')
                            ->copyable()
                            ->copyMessage('ID copiado'),
                        IconEntry::make('salesforce_synced')
                            ->label('Estado sincronización')
                            ->state(fn ($record): bool => filled($record->salesforce_case_id))
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('salesforce_case_error')
                            ->label('Error de sincronización')
                            ->placeholder('Sin errores')
                            ->color('danger')
                            ->visible(fn ($record): bool => filled($record->salesforce_case_error))
                            ->columnSpanFull()
                            ->wrap(),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function formatDynamicFields(mixed $state): array
    {
        if (! is_array($state) || $state === []) {
            return [];
        }

        $configuredFields = collect(self::fieldDefinitions())
            ->keyBy(fn (array $field): string => (string) $field['key']);

        $lines = [];

        foreach ($state as $key => $value) {
            $definition = $configuredFields->get((string) $key, []);
            $label = is_array($definition) && filled($definition['label'] ?? null)
                ? (string) $definition['label']
                : Str::headline((string) $key);

            $lines[$label] = self::formatDynamicValue($value, is_array($definition) ? $definition : []);
        }

        return $lines;
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
