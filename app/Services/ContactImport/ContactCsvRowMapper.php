<?php

namespace App\Services\ContactImport;

use Illuminate\Support\Str;

class ContactCsvRowMapper
{
    /**
     * @param  array<int, string>  $headers
     * @return array<int, array{source_column: string, target_field: string}>
     */
    public function buildSuggestedMappings(array $headers): array
    {
        return collect($headers)
            ->map(function (string $header): array {
                return [
                    'source_column' => $header,
                    'target_field' => $this->suggestTargetField($header),
                ];
            })
            ->values()
            ->all();
    }

    public function suggestTargetField(string $header): string
    {
        $signature = $this->headerSignature($header);

        $directMap = [
            'nombre' => 'name',
            'name' => 'name',
            'correo' => 'email',
            'email' => 'email',
            'celular' => 'phone',
            'telefono' => 'phone',
            'fono' => 'phone',
            'whatsapp' => 'phone',
            'rut' => 'rut',
            'comuna' => 'fields.comuna',
            'commune' => 'fields.comuna',
            'district' => 'fields.comuna',
            'proyecto' => 'fields.proyecto',
            'project' => 'fields.proyecto',
            'project_name' => 'fields.proyecto',
            'nombre_proyecto' => 'fields.proyecto',
            'comentario_cliente' => 'fields.mensaje',
            'comentario' => 'fields.mensaje',
            'mensaje' => 'fields.mensaje',
            'message' => 'fields.mensaje',
            'origen_del_prospecto' => 'fields.origen_prospecto',
            'medio_de_llegada' => 'fields.medio_llegada',
            'nombre_de_la_campana' => 'fields.campana',
        ];

        if (array_key_exists($signature, $directMap)) {
            return $directMap[$signature];
        }

        return 'fields.' . $signature;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<int, array{source_column: string, target_field: string}>  $mappings
     * @return array{name: string|null, email: string|null, phone: string|null, rut: string|null, fields: array<string, string>}
     */
    public function mapRow(array $row, array $mappings, bool $autoMapUnmapped = true): array
    {
        $result = [
            'name' => null,
            'email' => null,
            'phone' => null,
            'rut' => null,
            'fields' => [],
        ];

        $mappedColumns = [];

        foreach ($mappings as $mapping) {
            $sourceColumn = trim((string) ($mapping['source_column'] ?? ''));
            $targetField = trim((string) ($mapping['target_field'] ?? ''));

            if ($sourceColumn === '' || $targetField === '' || $targetField === 'skip') {
                continue;
            }

            $value = trim((string) ($row[$sourceColumn] ?? ''));

            if ($value === '') {
                continue;
            }

            $mappedColumns[] = $sourceColumn;

            if (in_array($targetField, ['name', 'email', 'phone', 'rut'], true)) {
                $result[$targetField] = $value;

                // Mirror to fields[] using the column's signature as key so that
                // dynamic table columns (which read from fields.{key}) also show the value.
                $mirrorKey = $this->headerSignature($sourceColumn);
                if ($mirrorKey !== '' && ! isset($result['fields'][$mirrorKey])) {
                    $result['fields'][$mirrorKey] = $value;
                }

                continue;
            }

            if (Str::startsWith($targetField, 'fields.')) {
                $fieldKey = trim((string) Str::after($targetField, 'fields.'));

                if ($fieldKey !== '') {
                    $result['fields'][$fieldKey] = $value;
                }
            }
        }

        if ($autoMapUnmapped) {
            foreach ($row as $header => $value) {
                if (in_array($header, $mappedColumns, true)) {
                    continue;
                }

                $cleanValue = trim((string) $value);
                if ($cleanValue === '') {
                    continue;
                }

                $signature = $this->headerSignature((string) $header);
                if ($signature === '') {
                    continue;
                }

                $result['fields'][$signature] = $cleanValue;
            }
        }

        if ($result['name'] === null && filled($result['fields']['name'] ?? null)) {
            $result['name'] = (string) $result['fields']['name'];
        }

        if ($result['email'] === null && filled($result['fields']['email'] ?? null)) {
            $result['email'] = (string) $result['fields']['email'];
        }

        if ($result['phone'] === null) {
            foreach (['phone', 'telefono', 'fono', 'celular', 'whatsapp'] as $fieldAlias) {
                if (filled($result['fields'][$fieldAlias] ?? null)) {
                    $result['phone'] = (string) $result['fields'][$fieldAlias];

                    break;
                }
            }
        }

        if ($result['rut'] === null && filled($result['fields']['rut'] ?? null)) {
            $result['rut'] = (string) $result['fields']['rut'];
        }

        return $result;
    }

    public function headerSignature(string $header): string
    {
        $signature = Str::of($header)
            ->ascii()
            ->lower()
            ->replace(':', ' ')
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return $signature !== '' ? $signature : 'campo';
    }
}
