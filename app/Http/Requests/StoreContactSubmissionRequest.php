<?php

namespace App\Http\Requests;

use App\Models\ContactChannel;
use App\Models\SiteSetting;
use App\Services\TurnstileVerificationService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContactSubmissionRequest extends FormRequest
{
    /**
     * @var array<int, string>|null
     */
    private ?array $selectedProjects = null;

    private ?ContactChannel $cachedChannel = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'fields' => ['required', 'array'],
            'channel' => ['nullable', 'string', 'max:100'],
            'turnstile_token' => $this->turnstileValidationRules(),
        ];

        foreach ($this->configuredFields() as $field) {
            $key = Str::of((string) ($field['key'] ?? ''))->trim()->toString();

            if ($key === '') {
                continue;
            }

            if (! $this->isFieldEnabledForSelectedProject($field)) {
                continue;
            }

            $type = (string) ($field['type'] ?? 'text');
            $required = (bool) ($field['required'] ?? false);

            $fieldRules = [$required ? 'required' : 'nullable'];

            if ($type === 'email') {
                $fieldRules[] = 'string';
                $fieldRules[] = 'email';
                $fieldRules[] = 'max:255';
            } elseif ($type === 'number') {
                $fieldRules[] = 'numeric';
            } elseif ($type === 'textarea') {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:5000';
            } elseif ($type === 'rut') {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:12';
                $fieldRules[] = function (string $attribute, mixed $value, Closure $fail): void {
                    if (blank($value)) {
                        return;
                    }

                    if (! $this->isValidRut((string) $value)) {
                        $fail('El campo :attribute debe contener un RUT válido.');
                    }
                };
            } elseif ($type === 'select') {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:255';

                $optionValues = array_keys($this->normalizedOptions($field));

                if ($optionValues !== [] && ! $this->isIncomeRangeFieldKey($key)) {
                    $fieldRules[] = Rule::in($optionValues);
                }
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:255';
            }

            $rules["fields.{$key}"] = $fieldRules;
        }

        foreach ($this->trackedMarketingFields() as $key => $label) {
            $rules["fields.{$key}"] = ['nullable', 'string', 'max:255'];
        }

        foreach ($this->supplementalContactFields() as $key => $ruleset) {
            if (array_key_exists("fields.{$key}", $rules)) {
                continue;
            }

            $rules["fields.{$key}"] = $ruleset;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'fields' => 'campos del formulario',
            'channel' => 'Canal de contacto',
            'turnstile_token' => 'verificacion de seguridad',
        ];

        foreach ($this->configuredFields() as $field) {
            $key = Str::of((string) ($field['key'] ?? ''))->trim()->toString();

            if ($key === '') {
                continue;
            }

            $attributes["fields.{$key}"] = (string) ($field['label'] ?? $key);
        }

        foreach ($this->trackedMarketingFields() as $key => $label) {
            $attributes["fields.{$key}"] = $label;
        }

        foreach (array_keys($this->supplementalContactFields()) as $key) {
            $attributes["fields.{$key}"] = $this->supplementalContactFieldLabels()[$key] ?? $key;
        }

        return $attributes;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateChannelField($validator);
            $this->validateRequiredContactSelectionFields($validator);

            if (! $this->isTurnstileEnabled()) {
                return;
            }

            if ($validator->errors()->has('turnstile_token')) {
                return;
            }

            $token = trim((string) $this->input('turnstile_token', ''));

            if ($token === '') {
                return;
            }

            $isValid = app(TurnstileVerificationService::class)->verify($token, $this->ip());

            if (! $isValid) {
                $validator->errors()->add('turnstile_token', 'No pudimos verificar la validacion de seguridad. Intenta nuevamente.');
            }
        });
    }

    private function validateChannelField(Validator $validator): void
    {
        if ($validator->errors()->has('channel')) {
            return;
        }

        $channelBody = trim((string) $this->input('channel', ''));

        if ($channelBody !== '' && ! ContactChannel::query()->where('slug', $channelBody)->where('is_active', true)->exists()) {
            $validator->errors()->add('channel', 'El canal de contacto seleccionado no es válido o está inactivo.');

            return;
        }

        if ($this->resolvedChannel() === null) {
            $validator->errors()->add('channel', 'El campo Canal de contacto es obligatorio.');
        }
    }

    /**
     * @return array<int, string|ValidationRule|Closure>
     */
    private function turnstileValidationRules(): array
    {
        if (! $this->isTurnstileEnabled()) {
            return ['nullable', 'string'];
        }

        return [
            'required',
            'string',
            'max:2048',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (blank($value)) {
                    return;
                }

                $service = new TurnstileVerificationService;
                if (! $service->verify((string) $value, $this->ip())) {
                    $fail('La validación de Turnstile falló. Por favor, intenta nuevamente.');
                }
            },
        ];
    }

    private function isTurnstileEnabled(): bool
    {
        return filled(config('services.turnstile.secret_key'));
    }

    /**
     * @return array<string, string>
     */
    private function trackedMarketingFields(): array
    {
        return [
            'utm_source' => 'UTM Source',
            'utm_medium' => 'UTM Medium',
            'utm_campaign' => 'UTM Campaign',
            'utm_term' => 'UTM Term',
            'utm_content' => 'UTM Content',
            'utm_site' => 'UTM Site',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function supplementalContactFields(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'fono' => ['nullable', 'string', 'max:255'],
            'celular' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:255'],
            'comuna' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'project_commune' => ['nullable', 'string', 'max:255'],
            'proyecto' => ['nullable', 'string', 'max:255'],
            'project' => ['nullable', 'string', 'max:255'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'nombre_proyecto' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'mensaje' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function supplementalContactFieldLabels(): array
    {
        return [
            'phone' => 'Telefono',
            'telefono' => 'Telefono',
            'fono' => 'Telefono',
            'celular' => 'Celular',
            'whatsapp' => 'WhatsApp',
            'comuna' => 'Comuna',
            'commune' => 'Comuna',
            'district' => 'Comuna',
            'project_commune' => 'Comuna del proyecto',
            'proyecto' => 'Proyecto',
            'project' => 'Proyecto',
            'project_name' => 'Proyecto',
            'nombre_proyecto' => 'Proyecto',
            'message' => 'Mensaje',
            'mensaje' => 'Mensaje',
        ];
    }

    private function validateRequiredContactSelectionFields(Validator $validator): void
    {
        $this->validateRequiredFieldGroup(
            validator: $validator,
            aliases: ['comuna', 'commune', 'district', 'project_commune'],
            errorKey: 'fields.comuna',
            label: 'Comuna',
        );

        $this->validateRequiredFieldGroup(
            validator: $validator,
            aliases: ['proyecto', 'project', 'project_name', 'nombre_proyecto'],
            errorKey: 'fields.proyecto',
            label: 'Proyecto',
        );
    }

    /**
     * @param  array<int, string>  $aliases
     */
    private function validateRequiredFieldGroup(Validator $validator, array $aliases, string $errorKey, string $label): void
    {
        if ($validator->errors()->has($errorKey)) {
            return;
        }

        foreach ($aliases as $alias) {
            $value = trim((string) $this->input("fields.{$alias}", ''));

            if ($value !== '') {
                return;
            }
        }

        $validator->errors()->add($errorKey, "El campo {$label} es obligatorio.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function configuredFields(): array
    {
        $channel = $this->resolvedChannel();

        $fields = $channel !== null
            ? $channel->effectiveFormFields()
            : SiteSetting::current()->contact_form_fields;

        if (! is_array($fields) || $fields === []) {
            return [
                ['key' => 'name', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'type' => 'email', 'required' => true],
                ['key' => 'message', 'type' => 'textarea', 'required' => true],
            ];
        }

        return $fields;
    }

    /**
     * Resolves the contact channel from the request.
     * Resolution order:
     *   1. Explicit `channel` field in the request body (required).
     *   2. `X-Contact-Channel` request header (for programmatic API clients).
     */
    public function resolvedChannel(): ?ContactChannel
    {
        if ($this->cachedChannel !== null) {
            return $this->cachedChannel;
        }

        // 1. Explicit channel slug in request body.
        $slug = trim((string) $this->input('channel', ''));

        if ($slug !== '') {
            $channel = ContactChannel::findBySlug($slug);

            if ($channel !== null) {
                return $this->cachedChannel = $channel;
            }
        }

        // 2. X-Contact-Channel header (fallback for programmatic API clients).
        $headerSlug = trim((string) $this->headers->get('X-Contact-Channel', ''));

        if ($headerSlug !== '') {
            $channel = ContactChannel::findBySlug($headerSlug);

            if ($channel !== null) {
                return $this->cachedChannel = $channel;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, string>
     */
    private function normalizedOptions(array $field): array
    {
        $normalized = [];

        foreach (($field['options'] ?? []) as $option) {
            if (is_array($option)) {
                $value = trim((string) ($option['value'] ?? $option['label'] ?? ''));
                $label = trim((string) ($option['label'] ?? $value));
            } else {
                $value = trim((string) $option);
                $label = $value;
            }

            if ($value === '') {
                continue;
            }

            $normalized[$value] = $label;
        }

        return $normalized;
    }

    private function isValidRut(string $value): bool
    {
        $cleaned = Str::of($value)
            ->upper()
            ->replaceMatches('/[^0-9K]/', '')
            ->toString();

        if (\strlen($cleaned) < 8 || \strlen($cleaned) > 9) {
            return false;
        }

        $body = substr($cleaned, 0, -1);
        $dv = strtolower(substr($cleaned, -1));

        if (! ctype_digit($body) || \strlen($body) < 7 || \strlen($body) > 8) {
            return false;
        }

        $sum = 0;
        $multiplier = 2;

        for ($i = \strlen($body) - 1; $i >= 0; $i--) {
            $sum += ((int) $body[$i]) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        $remainder = 11 - ($sum % 11);
        $expectedDv = match ($remainder) {
            11 => '0',
            10 => 'k',
            default => (string) $remainder,
        };

        return $dv === $expectedDv;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function isFieldEnabledForSelectedProject(array $field): bool
    {
        $fieldProjects = $this->normalizeProjects($field['projects'] ?? $field['proyectos'] ?? $field['project_types'] ?? []);

        if ($fieldProjects === []) {
            return true;
        }

        $selectedProjects = $this->resolveSelectedProjects();

        if ($selectedProjects === []) {
            return false;
        }

        return count(array_intersect($fieldProjects, $selectedProjects)) > 0;
    }

    /**
     * @return array<int, string>
     */
    private function resolveSelectedProjects(): array
    {
        if ($this->selectedProjects !== null) {
            return $this->selectedProjects;
        }

        $fields = $this->input('fields', []);

        if (! is_array($fields)) {
            $this->selectedProjects = [];

            return $this->selectedProjects;
        }

        $selectedRangeProjects = $this->resolveSelectedRangeProjects($fields);

        if ($selectedRangeProjects !== []) {
            $this->selectedProjects = $selectedRangeProjects;

            return $this->selectedProjects;
        }

        $projectName = trim((string) (
            $fields['proyecto']
            ?? $fields['project']
            ?? $fields['project_name']
            ?? $fields['nombre_proyecto']
            ?? ''
        ));

        if ($projectName === '') {
            $this->selectedProjects = [];

            return $this->selectedProjects;
        }

        $this->selectedProjects = $this->normalizeProjects([$projectName]);

        return $this->selectedProjects;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<int, string>
     */
    private function resolveSelectedRangeProjects(array $fields): array
    {
        $selectedRange = trim((string) (
            $fields['rango']
            ?? $fields['renta']
            ?? $fields['renta_liquida']
            ?? $fields['income_range']
            ?? ''
        ));

        if ($selectedRange === '') {
            return [];
        }

        $projects = [];

        foreach ($this->configuredFields() as $field) {
            $key = Str::of((string) ($field['key'] ?? ''))->trim()->toString();
            $type = (string) ($field['type'] ?? 'text');

            if (! in_array($key, ['rango', 'renta', 'renta_liquida', 'income_range'], true) || $type !== 'select') {
                continue;
            }

            if (! array_key_exists($selectedRange, $this->normalizedOptions($field))) {
                continue;
            }

            foreach (($field['options'] ?? []) as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $optionValue = trim((string) ($option['value'] ?? $option['label'] ?? ''));

                if ($optionValue !== $selectedRange) {
                    continue;
                }

                $optionProjects = $this->normalizeProjects($option['projects'] ?? $option['proyectos'] ?? $option['project_types'] ?? []);

                $projects = array_merge(
                    $projects,
                    $optionProjects !== []
                        ? $optionProjects
                        : $this->normalizeProjects($field['projects'] ?? $field['proyectos'] ?? $field['project_types'] ?? [])
                );
            }
        }

        return array_values(array_unique($projects));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeProjects(mixed $projects): array
    {
        if (is_string($projects)) {
            $projects = str_contains($projects, ',') ? explode(',', $projects) : [$projects];
        }

        if (! is_array($projects)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $project): string => Str::of((string) $project)->trim()->lower()->toString(),
            $projects
        ), static fn (string $project): bool => $project !== '')));
    }

    private function isIncomeRangeFieldKey(string $key): bool
    {
        return in_array($key, ['rango', 'renta', 'renta_liquida', 'income_range'], true);
    }
}
