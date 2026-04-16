<?php

namespace App\Services\Salesforce;

use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use Illuminate\Support\Str;

class SalesforceCaseMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(ContactSubmission $submission): array
    {
        $settings = SiteSetting::current();
        $fields = is_array($submission->fields) ? $submission->fields : [];
        $fieldLabels = $this->buildFieldLabels($settings->contact_form_fields);

        $subject = $this->fieldValue($fields, ['utm_campaign'])
            ?? (string) ($settings->site_name ?: config('app.name'));

        $payload = [
            'SuppliedName' => (string) ($settings->site_name ?: config('app.name')),
            'SuppliedEmail' => $settings->contact_notification_email ?: $settings->contact_email ?: $submission->email,
            'SuppliedPhone' => $submission->phone,
            'ContactPhone' => $submission->phone ?: $this->fieldValue($fields, ['phone', 'telefono', 'fono', 'celular', 'whatsapp']),
            'ContactEmail' => $submission->email ?: $this->fieldValue($fields, ['email', 'correo']),
            'RUT__c' => $submission->rut ?: $this->fieldValue($fields, ['rut']),
            'RecordTypeId' => config('services.salesforce.case_record_type_id'),
            'Status' => (string) config('services.salesforce.case_status', 'Nuevo'),
            'Origin' => (string) config('services.salesforce.case_origin', 'Web'),
            'Subject' => $subject,
            'Priority' => (string) config('services.salesforce.case_priority', 'Media'),
            'Description' => $this->buildDescription($fields, $fieldLabels),
            'OwnerId' => config('services.salesforce.case_owner_id'),
            'SourceId' => config('services.salesforce.case_source_id'),
            'Nombre_Proyecto__c' => $this->fieldValue($fields, ['nombre_proyecto', 'proyecto', 'project_name', 'proyecto_formulario']),
            'Proyecto_Formulario__c' => $this->fieldValue($fields, ['proyecto_formulario', 'proyecto', 'project_name']),
            'En_que_lugar__c' => $this->fieldValue($fields, ['comuna', 'commune']),
        ];

        return array_filter($payload, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, string>
     */
    private function buildFieldLabels(mixed $configuredFields): array
    {
        if (! is_array($configuredFields)) {
            return [];
        }

        $labels = [];

        foreach ($configuredFields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));
            $label = trim((string) ($field['label'] ?? ''));

            if ($key === '' || $label === '') {
                continue;
            }

            $labels[$key] = $label;
        }

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $fieldLabels
     */
    private function buildDescription(array $fields, array $fieldLabels): string
    {
        $lines = [];

        foreach ($fields as $key => $value) {
            $normalizedValue = $this->normalizeFieldValue($value);

            if ($normalizedValue === null || $normalizedValue === '') {
                continue;
            }

            $normalizedKey = (string) $key;
            $label = $fieldLabels[$normalizedKey] ?? $this->humanizeFieldKey($normalizedKey);
            $lines[] = sprintf('%s: %s', $label, $normalizedValue);
        }

        return implode("\n", $lines);
    }

    private function normalizeFieldValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if (is_array($value)) {
            $items = array_values(array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $value
            ), static fn (string $item): bool => $item !== ''));

            return $items === [] ? null : implode(', ', $items);
        }

        if (is_object($value)) {
            return \json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $aliases
     */
    private function fieldValue(array $fields, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (! array_key_exists($alias, $fields)) {
                continue;
            }

            $normalized = $this->normalizeFieldValue($fields[$alias]);

            if ($normalized !== null && $normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    private function humanizeFieldKey(string $key): string
    {
        $normalizedKey = strtolower(trim($key));

        if (str_starts_with($normalizedKey, 'utm_')) {
            $suffix = Str::of(substr($normalizedKey, 4))
                ->replace(['-', '_'], ' ')
                ->title()
                ->toString();

            return 'UTM '.$suffix;
        }

        return Str::of($key)
            ->replace(['-', '_'], ' ')
            ->trim()
            ->title()
            ->toString();
    }
}
