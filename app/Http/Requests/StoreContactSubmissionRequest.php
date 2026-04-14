<?php

namespace App\Http\Requests;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreContactSubmissionRequest extends FormRequest
{
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
}
