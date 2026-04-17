<?php

namespace App\Services\Salesforce;

use App\Models\ContactSubmission;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Collection;
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
        $utmCampaignDefault = $this->normalizeFieldValue(data_get($settings->extra_settings, 'utm_campaign_default')) ?: 'direct';
        $utmCampaign = $this->fieldValue($fields, ['utm_campaign']) ?: $utmCampaignDefault;
        $utmContent = $this->fieldValue($fields, ['utm_content']);
        $utmTerm = $this->fieldValue($fields, ['utm_term']);
        $leadSource = $utmSource ?: $this->fieldValue($fields, ['lead_source', 'medio_de_llegada', 'medio', 'origen']);
        $email = $submission->email ?: $this->fieldValue($fields, ['email', 'correo']) ?: null;
        $phone = $submission->phone ?: $this->fieldValue($fields, ['phone', 'telefono', 'fono', 'celular', 'whatsapp']);
        $commune = $this->fieldValue($fields, ['comuna', 'commune']);
        $incomeRange = $this->fieldValue($fields, ['rango', 'renta', 'renta_liquida', 'income_range']);
        $complementIncome = $this->fieldValue($fields, ['complementarenta', 'complementa_renta', 'complementa_renta_liquida', 'codeudor']);
        $incomeValidation = $this->fieldValue($fields, ['validacion_renta', 'validacion_de_renta', 'validacionrenta', 'validaci_n_renta']);
        $apartmentUsage = $this->fieldValue($fields, ['uso_departamento', 'usodepartamento', 'uso_departamento_inversion', 'buscas']);
        $employmentStatus = $this->fieldValue($fields, ['estado_laboral', 'estadolaboral', 'elaboral']);
        $investmentCommune = $this->fieldValue($fields, ['comuna_inversion', 'comunainversion', 'commune_investment'])
            ?: $commune;
        $projectSalesforceId = $this->resolveProjectSalesforceId($fields, $projectName);
        $projectAdvisorPhone = $this->resolveProjectAdvisorPhone($fields, $projectName);
        $normalizedLeadSource = $this->normalizeLeadSource($leadSource);
        $ownerId = $this->normalizeSalesforceId(config('services.salesforce.lead_owner_id') ?: config('services.salesforce.case_owner_id'));
        $ownerPhone = $projectAdvisorPhone;
        $wspOwnerPhone = $projectAdvisorPhone;
        $telefonoOwnerPhone = $projectAdvisorPhone;
        $whatsappPhone = $this->normalizePhone($phone)
            ?: $ownerPhone;
        $whatsappContactName = trim((string) ($firstName ?: config('services.salesforce.whatsapp_owner_name', 'ASESOR')));
        $whatsappLink = $this->buildWhatsappLink($whatsappPhone, $whatsappContactName);
        $whatsappLinkUrl = $whatsappLink !== null ? sprintf('<a href="%s" target="_blank">Link</a>', $whatsappLink) : null;

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
            'Rango_de_renta_liquida__c' => $incomeRange,
            'complementaRenta__c' => $complementIncome,
            'Validaci_n_Renta__c' => $incomeValidation,
            'usoDepartamento__c' => $apartmentUsage,
            'estadoLaboral__c' => $employmentStatus,
            'comunaInversion__c' => $investmentCommune,
            'Medio_de_Llegada__c' => $normalizedLeadSource,
            'Nombre_de_la_Campa_a__c' => $utmCampaign,
            'Audiencia__c' => $utmMedium,
            'Pieza_Grafica__c' => $utmContent,
            'wsp_owner__c' => $wspOwnerPhone,
            'Telefono_owner__c' => $telefonoOwnerPhone,
            'owner_phone__c' => $ownerPhone,
            'whatsapp_phone__c' => $whatsappPhone,
            'Whatsapp_Link__c' => $whatsappLink,
            'Whatsapp_Link_URL__c' => $whatsappLinkUrl,
            'utm_source__c' => $utmSource,
            'utm_medium__c' => $utmMedium,
            'utm_campaign__c' => $utmCampaign,
            'utm_content__c' => $utmContent,
            'utm_term__c' => $utmTerm,
        ];

        $payload = $this->normalizeLegacyCustomFieldsInPayload($payload);

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

    /**
     * @param  array<string, mixed>  $fields
     */
    private function resolveProjectAdvisorPhone(array $fields, ?string $projectName): ?string
    {
        $project = $this->resolveProject($fields, $projectName);

        if ($project === null) {
            return null;
        }

        /** @var Collection<int, \App\Models\Asesor> $asesores */
        $asesores = $project->asesores;

        $advisor = $asesores
            ->sortByDesc(static fn ($asesor): int => $asesor->is_active ? 1 : 0)
            ->first();

        return $this->normalizePhone($advisor?->whatsapp_owner);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function resolveProject(array $fields, ?string $projectName): ?Proyecto
    {
        $rawProjectId = $this->fieldValue($fields, ['proyecto_id', 'id_proyecto', 'project_id', 'proyecto_salesforce_id']);
        $normalizedProjectId = $this->normalizeSalesforceId($rawProjectId);

        $query = Proyecto::query()
            ->with(['asesores' => static function ($query): void {
                $query->select(['asesores.id', 'asesores.whatsapp_owner', 'asesores.is_active']);
            }]);

        if ($normalizedProjectId !== null) {
            return $query
                ->where('salesforce_id', $normalizedProjectId)
                ->first();
        }

        if ($projectName === null || trim($projectName) === '') {
            return null;
        }

        return $query
            ->where('name', $projectName)
            ->first();
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

    private function normalizePhone(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return preg_replace('/\s+/', '', $normalized) ?: null;
    }

    private function buildWhatsappLink(?string $phone, string $ownerName): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digitsOnlyPhone = preg_replace('/\D+/', '', $phone);

        if ($digitsOnlyPhone === null || $digitsOnlyPhone === '') {
            return null;
        }

        $normalizedOwnerName = trim($ownerName);

        if ($normalizedOwnerName === '') {
            $normalizedOwnerName = 'ASESOR';
        }

        $message = sprintf('Hola %s, te contacto desde Leben. ¿Tienes un minuto?', Str::upper($normalizedOwnerName));

        return sprintf('https://wa.me/%s?text=%s', $digitsOnlyPhone, rawurlencode($message));
    }

    private function normalizeLegacyFieldValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        return str_replace(' ', '_', $normalized);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeLegacyCustomFieldsInPayload(array $payload): array
    {
        $excludedCustomFields = [
            'Email__c',
            'RUT__c',
            'Proyecto__c',
            'ID_Proyecto__c',
            'wsp_owner__c',
            'Telefono_owner__c',
            'owner_phone__c',
            'whatsapp_phone__c',
            'Whatsapp_Link__c',
            'Whatsapp_Link_URL__c',
            'utm_source__c',
            'utm_medium__c',
            'utm_campaign__c',
            'utm_content__c',
            'utm_term__c',
            'Rango_de_renta_liquida__c',
            'complementaRenta__c',
            'Validaci_n_Renta__c',
            'usoDepartamento__c',
            'estadoLaboral__c',
            'comunaInversion__c',
        ];

        foreach ($payload as $field => $value) {
            if (! is_string($value)) {
                continue;
            }

            if (! str_ends_with($field, '__c')) {
                continue;
            }

            if (in_array($field, $excludedCustomFields, true)) {
                continue;
            }

            $payload[$field] = $this->normalizeLegacyFieldValue($value);
        }

        return $payload;
    }
}
