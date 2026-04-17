<?php

namespace App\Http\Requests;

use App\Models\Proyecto;
use App\Models\SiteSetting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreContactSubmissionRequest extends FormRequest
{
    /**
     * @var array<int, string>|null
     */
    private ?array $selectedProjectTypes = null;

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

                if ($optionValues !== []) {
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function configuredFields(): array
    {
        $settings = SiteSetting::current();
        $fields = $settings->contact_form_fields;

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
        $fieldProjectTypes = $this->normalizeProjectTypes($field['project_types'] ?? []);

        if ($fieldProjectTypes === []) {
            return true;
        }

        $selectedProjectTypes = $this->resolveSelectedProjectTypes();

        if ($selectedProjectTypes === []) {
            return false;
        }

        return count(array_intersect($fieldProjectTypes, $selectedProjectTypes)) > 0;
    }

    /**
     * @return array<int, string>
     */
    private function resolveSelectedProjectTypes(): array
    {
        if ($this->selectedProjectTypes !== null) {
            return $this->selectedProjectTypes;
        }

        $fields = $this->input('fields', []);

        if (! is_array($fields)) {
            $this->selectedProjectTypes = [];

            return $this->selectedProjectTypes;
        }

        $selectedRangeProjectTypes = $this->resolveSelectedRangeProjectTypes($fields);

        if ($selectedRangeProjectTypes !== []) {
            $this->selectedProjectTypes = $selectedRangeProjectTypes;

            return $this->selectedProjectTypes;
        }

        $projectName = trim((string) (
            $fields['proyecto']
            ?? $fields['project']
            ?? $fields['project_name']
            ?? $fields['nombre_proyecto']
            ?? ''
        ));

        if ($projectName === '') {
            $this->selectedProjectTypes = [];

            return $this->selectedProjectTypes;
        }

        $projectCommune = trim((string) (
            $fields['comuna']
            ?? $fields['commune']
            ?? $fields['district']
            ?? $fields['project_commune']
            ?? ''
        ));

        $query = Proyecto::query()
            ->where('is_active', true)
            ->where('name', $projectName);

        if ($projectCommune !== '') {
            $query->where('comuna', $projectCommune);
        }

        $project = $query->first();

        if (! $project instanceof Proyecto) {
            $project = Proyecto::query()
                ->where('is_active', true)
                ->where('name', $projectName)
                ->first();
        }

        if (! $project instanceof Proyecto) {
            $this->selectedProjectTypes = [];

            return $this->selectedProjectTypes;
        }

        $this->selectedProjectTypes = $this->normalizeProjectTypes($project->tipo);

        return $this->selectedProjectTypes;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<int, string>
     */
    private function resolveSelectedRangeProjectTypes(array $fields): array
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

        $projectTypes = [];

        foreach ($this->configuredFields() as $field) {
            $key = Str::of((string) ($field['key'] ?? ''))->trim()->toString();
            $type = (string) ($field['type'] ?? 'text');

            if (! in_array($key, ['rango', 'renta', 'renta_liquida', 'income_range'], true) || $type !== 'select') {
                continue;
            }

            if (! array_key_exists($selectedRange, $this->normalizedOptions($field))) {
                continue;
            }

            $projectTypes = array_merge($projectTypes, $this->normalizeProjectTypes($field['project_types'] ?? []));
        }

        return array_values(array_unique($projectTypes));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeProjectTypes(mixed $types): array
    {
        if (is_string($types)) {
            $types = str_contains($types, ',') ? explode(',', $types) : [$types];
        }

        if (! is_array($types)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $type): string => Str::of((string) $type)->trim()->lower()->toString(),
            $types
        ), static fn (string $type): bool => $type !== '')));
    }
}
