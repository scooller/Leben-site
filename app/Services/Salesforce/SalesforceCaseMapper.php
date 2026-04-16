<?php

namespace App\Services\Salesforce;

use App\Models\ContactSubmission;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use Illuminate\Support\Str;

class SalesforceCaseMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(ContactSubmission $submission): array
    {
        return $this->mapLead($submission);
    }

    /**
     * @return array<string, mixed>
     */
    public function mapLead(ContactSubmission $submission): array
    {
        $settings = SiteSetting::current();
        $fields = is_array($submission->fields) ? $submission->fields : [];
        $fieldLabels = $this->buildFieldLabels($settings->contact_form_fields);

        $fullName = $this->fieldValue($fields, ['name', 'nombre']) ?: $submission->name;
        $firstName = $this->fieldValue($fields, ['first_name', 'nombre'])
            ?: $this->extractFirstName($fullName);
        $lastName = $this->fieldValue($fields, ['last_name', 'lastname', 'apellido'])
            ?: $this->extractLastName($fullName)
            ?: 'Sin Apellido';

        $projectName = $this->fieldValue($fields, ['nombre_proyecto', 'proyecto', 'project_name', 'proyecto_formulario']);
        $utmSource = $this->fieldValue($fields, ['utm_source']);
        $utmMedium = $this->fieldValue($fields, ['utm_medium']);
        $utmCampaign = $this->fieldValue($fields, ['utm_campaign']);
        $utmContent = $this->fieldValue($fields, ['utm_content']);
        $utmTerm = $this->fieldValue($fields, ['utm_term']);
        $leadSource = $this->fieldValue($fields, ['medio', 'medio_de_llegada', 'lead_source', 'origen']) ?: $utmSource;
        $email = $submission->email ?: $this->fieldValue($fields, ['email', 'correo']) ?: null;
        $phone = $submission->phone ?: $this->fieldValue($fields, ['phone', 'telefono', 'fono', 'celular', 'whatsapp']);
        $commune = $this->fieldValue($fields, ['comuna', 'commune']);
        $projectSalesforceId = $this->resolveProjectSalesforceId($fields, $projectName);
        $normalizedLeadSource = $this->normalizeLeadSource($leadSource);
        $ownerId = $this->normalizeSalesforceId(config('services.salesforce.lead_owner_id') ?: config('services.salesforce.case_owner_id'));

        $payload = [
            'FirstName' => $firstName,
            'LastName' => $lastName,
            'Company' => (string) ($settings->site_name ?: config('app.name') ?: 'iLeben'),
            'Phone' => $phone,
            'MobilePhone' => $phone,
            'Email' => $email,
            'Email__c' => $email,
            'RUT__c' => $submission->rut ?: $this->fieldValue($fields, ['rut']),
            'Status' => (string) config('services.salesforce.lead_status', 'En Contacto'),
            'OwnerId' => $ownerId,
            'LeadSource' => $normalizedLeadSource,
            'Description' => $this->buildDescription($fields, $fieldLabels),
            'Tipo_Ingreso__c' => 'Online',
            'Proyecto__c' => $projectSalesforceId,
            'ID_Proyecto__c' => $projectSalesforceId,
            'Informacion_Cotizacion__c' => $projectName,
            'Proyect_ID__c' => $projectName,
            'Comuna__c' => $commune,
            'Medio_de_Llegada__c' => $normalizedLeadSource,
            'Nombre_de_la_Campa_a__c' => $utmCampaign,
            'Audiencia__c' => $utmMedium,
            'Pieza_Grafica__c' => $utmContent,
            'utm_source__c' => $utmSource,
            'utm_medium__c' => $utmMedium,
            'utm_campaign__c' => $utmCampaign,
            'utm_content__c' => $utmContent,
            'utm_term__c' => $utmTerm,
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

    private function extractFirstName(?string $fullName): ?string
    {
        $normalized = trim((string) $fullName);

        if ($normalized === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $normalized) ?: [];

        return $parts[0] ?? null;
    }

    private function extractLastName(?string $fullName): ?string
    {
        $normalized = trim((string) $fullName);

        if ($normalized === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $normalized) ?: [];

        if (count($parts) <= 1) {
            return null;
        }

        return implode(' ', array_slice($parts, 1));
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function resolveProjectSalesforceId(array $fields, ?string $projectName): ?string
    {
        $rawProjectId = $this->fieldValue($fields, ['proyecto_id', 'id_proyecto', 'project_id', 'proyecto_salesforce_id']);

        $normalizedProjectId = $this->normalizeSalesforceId($rawProjectId);

        if ($normalizedProjectId !== null) {
            return $normalizedProjectId;
        }

        if ($projectName === null || trim($projectName) === '') {
            return null;
        }

        $project = Proyecto::query()
            ->select(['salesforce_id'])
            ->where('name', $projectName)
            ->first();

        return $this->normalizeSalesforceId($project?->salesforce_id);
    }

    private function normalizeLeadSource(?string $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return ucfirst(strtolower($normalized));
    }

    private function normalizeSalesforceId(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^[a-zA-Z0-9]{15,18}$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }
}
