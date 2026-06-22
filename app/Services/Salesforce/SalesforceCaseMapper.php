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
		$isCsvImport = $this->isCsvImportSubmission($submission);
		$extraSettings = is_array($settings->extra_settings) ? $settings->extra_settings : [];
		$fieldLabels = $this->buildFieldLabels($settings->contact_form_fields);

		$fullName = $this->fieldValue($fields, ['name', 'nombre']) ?: $submission->name;
		$firstName = $this->fieldValue($fields, ['first_name', 'nombre'])
			?: $this->extractFirstName($fullName);
		$lastName = $this->fieldValue($fields, ['last_name', 'lastname', 'apellido'])
			?: $this->extractLastName($fullName)
			?: 'Sin Apellido';

		$projectName = $this->fieldValue($fields, ['nombre_proyecto', 'proyecto', 'project_name', 'proyecto_formulario', 'project']);
		$quotationInfo = $this->fieldValue($fields, [
			'informacion_cotizacion',
			'informacion_cotizaci_n',
			'informacion_cotizacion_web',
			'quote_information',
		]) ?: $projectName;
		$utmSourceInput = $this->fieldValue($fields, [
			'utm_source',
			'lead_source',
		]);
		$utmSourceDefault = $isCsvImport
			? null
			: ($this->normalizeFieldValue($extraSettings['utm_source_default'] ?? null) ?: 'direct');
		$utmSourceValue = $utmSourceInput ?: $utmSourceDefault;
		$arrivalMedium = $this->fieldValue($fields, [
			'medio_de_llegada',
			'medio_llegada',
		])
			?: $this->fieldValue($fields, ['origen_del_prospecto', 'origen_prospecto'])
			?: $utmSourceValue;
		$originProspect = $this->fieldValue($fields, [
			'origen_del_prospecto',
			'origen_prospecto',
		]);
		$utmSiteDefault = $this->normalizeFieldValue($extraSettings['utm_site_default'] ?? null);
		$website = $this->resolveWebsiteSource($submission, $fields, $utmSourceInput, $utmSiteDefault);
		$utmMediumDefault = $isCsvImport
			? null
			: ($this->normalizeFieldValue($extraSettings['utm_medium_default'] ?? null) ?: 'organic');
		$utmMedium = $this->fieldValue($fields, ['utm_medium', 'audiencia'])
			?: $this->fieldValue($fields, ['medio_de_llegada', 'medio_llegada', 'origen_del_prospecto', 'origen_prospecto'])
			?: $utmMediumDefault;
		$utmCampaignDefault = $isCsvImport
			? null
			: $this->normalizeFieldValue($extraSettings['utm_campaign_default'] ?? null);
		$utmCampaign = $this->resolveUtmCampaign($fields, $utmCampaignDefault);
		$utmContentDefault = $this->normalizeFieldValue($extraSettings['utm_content_default'] ?? null) ?: 'none';
		$utmContent = $this->fieldValue($fields, ['utm_content', 'pieza_grafica']) ?: $utmContentDefault;
		$utmTermDefault = $isCsvImport
			? null
			: ($this->normalizeFieldValue($extraSettings['utm_term_default'] ?? null) ?: 'none');
		$utmTerm = $this->fieldValue($fields, ['utm_term', 'audiencia']) ?: $utmTermDefault;
		$leadSource = $this->resolveLeadSource($fields, $utmTerm, $arrivalMedium);
		$email = $submission->email ?: $this->fieldValue($fields, ['email', 'correo']) ?: null;
		$phone = $submission->phone ?: $this->fieldValue($fields, ['phone', 'telefono', 'fono', 'celular', 'whatsapp']);
		$includeDescription = $this->shouldIncludeDescription($settings);
		$commune = $this->fieldValue($fields, ['comuna', 'commune']);
		$incomeRange = $this->fieldValue($fields, ['rango_renta', 'rango_de_renta', 'en_que_rango_se_encuentra_tu_renta_liquida', 'rango', 'renta', 'renta_liquida', 'income_range']);
		$complementIncome = $this->fieldValue($fields, ['complementarenta', 'complementa_renta', 'complementa_renta_liquida', 'codeudor']);
		$incomeValidation = $this->fieldValue($fields, ['validacion_renta', 'validacion_de_renta', 'validacionrenta', 'validaci_n_renta']);
		$apartmentUsage = $this->fieldValue($fields, ['uso_departamento', 'usodepartamento', 'uso_departamento_inversion', 'buscas']);
		$employmentStatus = $this->fieldValue($fields, ['estado_laboral', 'estadolaboral', 'elaboral']);
		$investmentCommune = $this->fieldValue($fields, ['comuna_inversion', 'comunainversion', 'commune_investment'])
			?: $commune;
		$projectSalesforceId = $this->resolveProjectSalesforceId($fields, $projectName);
		$projectAdvisorPhone = $this->resolveProjectAdvisorPhone($fields, $projectName);
		$normalizedLeadSource = $this->normalizeLeadSource($leadSource, $isCsvImport);
		$ownerId = $this->resolveLeadOwnerId();
		$ownerPhone = $projectAdvisorPhone;
		$wspOwnerPhone = $projectAdvisorPhone;
		$telefonoOwnerPhone = $projectAdvisorPhone;
		$notas = $this->fieldValue($fields, ['nota', 'notas']);
		$comentarioCliente = $this->fieldValue($fields, ['cliente_comentario', 'coment_cli', 'comentario_cliente', 'comentarios', 'comentario', 'message']);

		$payload = [
			'FirstName' => $firstName,
			'LastName' => $lastName,
			// 'Company' => (string) ($settings->site_name ?: config('app.name') ?: 'iLeben'),
			'Company' => '', // Campo "Company" obligatorio en Lead, pero lo dejamos vacío por ser un lead de consumidor final sin empresa asociada.
			'Phone' => $phone,
			'MobilePhone' => $phone,
			'Email' => $email,
			'Website' => $website,
			'Email__c' => $email,
			'RUT__c' => $submission->rut ?: $this->fieldValue($fields, ['rut']),
			'Status' => (string) config('services.salesforce.lead_status', 'En Contacto'),
			'OwnerId' => $ownerId,
			'LeadSource' => $normalizedLeadSource,
			'Description' => $includeDescription
				? $this->buildDescription($fields, $fieldLabels)
				: null,
			'Tipo_Ingreso__c' => 'Online',
			'Proyecto__c' => $projectSalesforceId,
			'ID_Proyecto__c' => $projectSalesforceId,
			'Informacion_Cotizacion__c' => $quotationInfo,
			// 'Proyect_ID__c' => $projectName,
			'Comuna__c' => $commune,
			'Rango_de_renta_liquida__c' => $incomeRange,
			'complementaRenta__c' => $complementIncome,
			'Validaci_n_Renta__c' => $incomeValidation,
			'usoDepartamento__c' => $apartmentUsage,
			'estadoLaboral__c' => $employmentStatus,
			'comunaInversion__c' => $investmentCommune,
			'Medio_de_Llegada__c' => $arrivalMedium,
			'Nombre_de_la_Campa_a__c' => $utmCampaign,
			'Audiencia__c' => $utmMedium,
			'Pieza_Grafica__c' => $utmContent,
			'wsp_owner__c' => $wspOwnerPhone,
			'Telefono_owner__c' => $telefonoOwnerPhone,
			'owner_phone__c' => $ownerPhone,
			'utm_source__c' => $arrivalMedium,
			'utm_medium__c' => $utmMedium,
			'utm_campaign__c' => $utmCampaign,
			'utm_content__c' => $utmContent,
			'utm_term__c' => $utmTerm,
			'Notas__c' => $notas,
			'Comentario_Cliente__c' => $comentarioCliente,
			'GenderIdentity' => 'OTRO',
		];


		$payload = $this->normalizeLegacyCustomFieldsInPayload($payload);

		return array_filter(
			$payload,
			static fn(mixed $value, string $field): bool => $field === 'Company'
				? $value !== null
				: $value !== null && $value !== '',
			ARRAY_FILTER_USE_BOTH
		);
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

	private function shouldIncludeDescription(SiteSetting $settings): bool
	{
		$configured = data_get($settings->extra_settings, 'salesforce_include_description');

		if ($configured === null) {
			return true;
		}

		return (bool) $configured;
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
				static fn(mixed $item): string => trim((string) $item),
				$value
			), static fn(string $item): bool => $item !== ''));

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
			$normalizedAlias = $this->normalizeFieldKey($alias);

			if (! array_key_exists($normalizedAlias, $fields)) {
				continue;
			}

			$normalized = $this->normalizeFieldValue($fields[$normalizedAlias]);

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

			return 'UTM ' . $suffix;
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

		$normalizedProjectNameId = $this->normalizeSalesforceId($projectName);

		if ($normalizedProjectNameId !== null) {
			return $normalizedProjectNameId;
		}

		$project = $this->resolveProjectByName($projectName);

		return $this->normalizeSalesforceId($project?->salesforce_id);
	}

	private function resolveProjectByName(?string $projectName): ?Proyecto
	{
		if ($projectName === null || trim($projectName) === '') {
			return null;
		}

		$project = Proyecto::query()
			->select(['id', 'salesforce_id', 'name', 'slug'])
			->where('name', $projectName)
			->first();

		if ($project !== null) {
			return $project;
		}

		$project = Proyecto::query()
			->select(['id', 'salesforce_id', 'name', 'slug'])
			->whereRaw('LOWER(name) = ?', [mb_strtolower($projectName)])
			->first();

		if ($project !== null) {
			return $project;
		}

		$signature = $this->textSignature($projectName);

		$project = Proyecto::query()
			->select(['id', 'salesforce_id', 'name', 'slug'])
			->whereRaw('LOWER(slug) = ?', [$signature])
			->first();

		if ($project !== null) {
			return $project;
		}

		foreach (Proyecto::query()->select(['id', 'salesforce_id', 'name', 'slug'])->get() as $candidate) {
			if ($this->textSignature($candidate->name) === $signature) {
				return $candidate;
			}
		}

		return null;
	}

	private function textSignature(string $value): string
	{
		return Str::of($value)
			->ascii()
			->lower()
			->replace(':', ' ')
			->replaceMatches('/[^a-z0-9]+/', '_')
			->trim('_')
			->toString();
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
			->sortByDesc(static fn($asesor): int => $asesor->is_active ? 1 : 0)
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

		if ($normalizedProjectId !== null) {
			return Proyecto::query()
				->with(['asesores' => static function ($query): void {
					$query->select(['asesores.id', 'asesores.whatsapp_owner', 'asesores.is_active']);
				}])
				->where('salesforce_id', $normalizedProjectId)
				->first();
		}

		$normalizedProjectNameId = $this->normalizeSalesforceId($projectName);

		if ($normalizedProjectNameId !== null) {
			return Proyecto::query()
				->with(['asesores' => static function ($query): void {
					$query->select(['asesores.id', 'asesores.whatsapp_owner', 'asesores.is_active']);
				}])
				->where('salesforce_id', $normalizedProjectNameId)
				->first();
		}

		$project = $this->resolveProjectByName($projectName);

		if ($project === null) {
			return null;
		}

		return $project->load(['asesores' => static function ($query): void {
			$query->select(['asesores.id', 'asesores.whatsapp_owner', 'asesores.is_active']);
		}]);
	}

	private function normalizeLeadSource(?string $value, bool $preserveOriginalCase = false): ?string
	{
		$normalized = trim((string) $value);

		if ($normalized === '') {
			return null;
		}

		if ($preserveOriginalCase) {
			return $normalized;
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

	private function resolveLeadOwnerId(): ?string
	{
		$leadOwnerId = $this->normalizeSalesforceId(config('services.salesforce.lead_owner_id'));

		if ($leadOwnerId !== null) {
			return $leadOwnerId;
		}

		return $this->normalizeSalesforceId(config('services.salesforce.case_owner_id'));
	}

	private function normalizePhone(mixed $value): ?string
	{
		$normalized = trim((string) $value);

		if ($normalized === '') {
			return null;
		}

		return preg_replace('/\s+/', '', $normalized) ?: null;
	}

	/**
	 * @param  array<string, mixed>  $fields
	 */
	private function resolveWebsiteSource(ContactSubmission $submission, array $fields, ?string $utmSource, ?string $utmSiteDefault = null): ?string
	{
		$channelWebsite = $this->resolveChannelWebsite($submission);

		if ($channelWebsite !== null) {
			return $channelWebsite;
		}

		return $this->fieldValue($fields, [
			'utm_site',
			'website',
			'site',
			'sitio_web',
			'sitio',
			'origen_sitio',
			'source_site',
			'origin_site',
			'medio_de_llegada',
			'medio_llegada',
			'origen_del_prospecto',
			'origen_prospecto',
			'referrer',
		]) ?: $utmSiteDefault ?: $utmSource;
	}

	private function resolveChannelWebsite(ContactSubmission $submission): ?string
	{
		$channel = $submission->channel;

		if ($channel === null) {
			return null;
		}

		foreach ((array) ($channel->domain_patterns ?? []) as $pattern) {
			$normalized = $this->normalizeDomainPattern((string) $pattern);

			if ($normalized !== null) {
				return $normalized;
			}
		}

		return null;
	}

	private function normalizeDomainPattern(string $pattern): ?string
	{
		$normalized = strtolower(trim($pattern));

		if ($normalized === '') {
			return null;
		}

		$normalized = str_replace(['*.', '*'], '', $normalized);
		$normalized = ltrim($normalized, '.');

		return $normalized !== '' ? $normalized : null;
	}

	/**
	 * @param  array<string, mixed>  $fields
	 */
	private function resolveUtmCampaign(array $fields, ?string $defaultValue): string
	{
		if ($defaultValue !== null && trim($defaultValue) !== '') {
			return $defaultValue;
		}

		$campaign = $this->fieldValue($fields, ['utm_campaign', 'campana', 'nombre_de_la_campana']);

		if ($campaign === null) {
			return 'campaign';
		}

		if (in_array(strtolower(trim($campaign)), ['auto-tagging', 'campaign'], true)) {
			return 'campaign';
		}

		return $campaign;
	}

	/**
	 * @param  array<string, mixed>  $fields
	 */
	private function resolveLeadSource(array $fields, ?string $utmTerm, ?string $arrivalMedium): ?string
	{
		return $utmTerm
			?: $this->fieldValue($fields, [
				'origen_del_prospecto',
				'origen_prospecto',
				'lead_source',
			])
			?: $arrivalMedium;
	}

	private function isCsvImportSubmission(ContactSubmission $submission): bool
	{
		$userAgent = strtolower(trim((string) $submission->user_agent));

		if ($userAgent === '') {
			return false;
		}

		return str_contains($userAgent, 'filament-csv-import');
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
	/*
		Extra metodos
	*/
	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	private function normalizeFieldKeys(array $fields): array
	{
		$normalized = [];

		foreach ($fields as $key => $value) {
			$normalizedKey = $this->normalizeFieldKey((string) $key);
			$normalized[$normalizedKey] = $value;
		}

		return $normalized;
	}

	private function normalizeFieldKey(string $key): string
	{
		return Str::of($key)
			->ascii()                 // quita tildes y translitera ñ -> n
			->lower()                 // minúsculas
			->replaceMatches('/[^a-z0-9]+/', '_') // espacios y separadores a _
			->trim('_')
			->toString();
	}
}
