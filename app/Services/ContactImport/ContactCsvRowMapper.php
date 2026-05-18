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
            'apellidos' => 'fields.apellido',
            'apellido' => 'fields.apellido',
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
            'campana' => 'fields.campana',
            'audiencia' => 'fields.utm_medium',
            'pieza_grafica' => 'fields.utm_content',
            'rango_de_renta' => 'fields.rango_renta',
            'en_que_rango_se_encuentra_tu_renta_liquida' => 'fields.rango_renta',
            'tienes_la_posibilidad_de_complementar_tu_renta' => 'fields.codeudor',
        ];

        if (array_key_exists($signature, $directMap)) {
            return $directMap[$signature];
        }

        return 'fields.'.$signature;
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

            $rawValue = trim((string) ($row[$sourceColumn] ?? ''));

            if ($rawValue === '') {
                continue;
            }

            $mappedColumns[] = $sourceColumn;
            $sourceSignature = $this->headerSignature($sourceColumn);
            $value = $this->normalizeMappedValue($rawValue, $sourceSignature, $targetField);

            if ($value === '') {
                continue;
            }

            if ($sourceSignature !== '' && ! isset($result['fields'][$sourceSignature])) {
                $result['fields'][$sourceSignature] = $value;
            }

            $this->applyFieldAliases($result['fields'], $sourceSignature, $value);

            if (in_array($targetField, ['name', 'email', 'phone', 'rut'], true)) {
                $result[$targetField] = $value;

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

                $normalizedValue = $this->normalizeMappedValue($cleanValue, $signature, 'fields.'.$signature);

                if ($normalizedValue === '') {
                    continue;
                }

                $result['fields'][$signature] = $normalizedValue;
                $this->applyFieldAliases($result['fields'], $signature, $normalizedValue);
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

        $result['fields'] = $this->canonicalizeFields($result['fields']);

        return $result;
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    private function canonicalizeFields(array $fields): array
    {
        $normalized = $fields;

        $canonicalApellido = trim((string) ($normalized['apellido'] ?? ''));
        $apellidosValue = trim((string) ($normalized['apellidos'] ?? ''));

        if ($canonicalApellido === '' && $apellidosValue !== '') {
            $canonicalApellido = $apellidosValue;
        }

        unset($normalized['apellidos']);

        if ($canonicalApellido !== '') {
            $normalized['apellido'] = $canonicalApellido;
        }

        $rangoAliases = [
            'rango_de_renta',
            'en_que_rango_se_encuentra_tu_renta_liquida',
        ];

        $canonicalRango = trim((string) ($normalized['rango_renta'] ?? ''));

        if ($canonicalRango === '') {
            foreach ($rangoAliases as $aliasKey) {
                $aliasValue = trim((string) ($normalized[$aliasKey] ?? ''));

                if ($aliasValue === '') {
                    continue;
                }

                $canonicalRango = $aliasValue;
                break;
            }
        }

        foreach ($rangoAliases as $aliasKey) {
            unset($normalized[$aliasKey]);
        }

        if ($canonicalRango !== '') {
            $normalized['rango_renta'] = $canonicalRango;
        }

        $utmAliasCandidates = [
            'utm_campaign' => ['campana', 'nombre_de_la_campana'],
            'utm_content' => ['pieza_grafica'],
            'utm_medium' => ['audiencia'],
            'utm_term' => ['audiencia'],
        ];

        foreach ($utmAliasCandidates as $utmKey => $aliases) {
            $utmValue = trim((string) ($normalized[$utmKey] ?? ''));

            if ($utmValue !== '') {
                continue;
            }

            foreach ($aliases as $aliasKey) {
                $aliasValue = trim((string) ($normalized[$aliasKey] ?? ''));

                if ($aliasValue === '') {
                    continue;
                }

                $normalized[$utmKey] = $aliasValue;
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function applyFieldAliases(array &$fields, string $sourceKey, string $value): void
    {
        $aliasesBySource = [
            'apellidos' => ['apellido'],
            'celular' => ['telefono', 'phone'],
            'telefono' => ['celular', 'phone'],
            'fono' => ['telefono', 'phone'],
            'phone' => ['telefono', 'celular'],
            'en_que_rango_se_encuentra_tu_renta_liquida' => ['rango_renta'],
            'tienes_la_posibilidad_de_complementar_tu_renta' => ['codeudor'],
            'medio_de_llegada' => ['medio_llegada'],
            'medio_llegada' => ['medio_de_llegada'],
            'nombre_de_la_campana' => ['campana', 'utm_campaign'],
            'campana' => ['nombre_de_la_campana', 'utm_campaign'],
            'pieza_grafica' => ['utm_content'],
            'audiencia' => ['utm_medium', 'utm_term'],
            'informacion_cotizacion' => ['project_name', 'nombre_proyecto'],
            'origen_del_prospecto' => ['origen_prospecto'],
            'origen_prospecto' => ['origen_del_prospecto'],
        ];

        foreach ($aliasesBySource[$sourceKey] ?? [] as $aliasKey) {
            if (! isset($fields[$aliasKey])) {
                $fields[$aliasKey] = $value;
            }
        }
    }

    private function normalizeMappedValue(string $value, string $sourceKey, string $targetField): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        $isPhoneTarget = in_array($targetField, ['phone', 'fields.phone', 'fields.telefono', 'fields.celular'], true);
        $isPhoneSource = in_array($sourceKey, ['phone', 'telefono', 'celular', 'fono', 'whatsapp'], true);

        if ($isPhoneTarget || $isPhoneSource) {
            return $this->normalizePhoneNumber($normalized);
        }

        $isCodeudorTarget = $targetField === 'fields.codeudor';
        $isCodeudorSource = in_array($sourceKey, [
            'codeudor',
            'tienes_la_posibilidad_de_complementar_tu_renta',
            'complementa_renta',
            'complementarenta',
        ], true);

        if ($isCodeudorTarget || $isCodeudorSource) {
            return $this->normalizeYesNoValue($normalized);
        }

        $isEmailTarget = in_array($targetField, ['email', 'fields.email'], true);
        $isEmailSource = in_array($sourceKey, ['email', 'correo'], true);

        if ($isEmailTarget || $isEmailSource) {
            return $normalized;
        }

        return $this->normalizeTextValue($normalized);
    }

    private function normalizePhoneNumber(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    private function normalizeYesNoValue(string $value): string
    {
        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        if (str_contains($normalized, 'no_puedo') || str_starts_with($normalized, 'no')) {
            return 'No';
        }

        if (str_contains($normalized, 'puedo') || str_starts_with($normalized, 'si') || str_starts_with($normalized, 'yes')) {
            return 'Si';
        }

        return $value;
    }

    private function normalizeTextValue(string $value): string
    {
        $normalized = str_replace('_', ' ', $value);
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
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
