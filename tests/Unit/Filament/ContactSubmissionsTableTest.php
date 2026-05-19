<?php

namespace Tests\Unit\Filament;

use App\Filament\Resources\ContactSubmissions\ContactSubmissions\Tables\ContactSubmissionsTable;
use ReflectionClass;
use Tests\TestCase;

class ContactSubmissionsTableTest extends TestCase
{
    public function test_resolve_dynamic_field_value_returns_rango_renta_for_income_range_key(): void
    {
        $fields = [
            'rango_renta' => 'entre $4.500.000 a $6.500.000',
        ];

        $value = $this->invokeResolveDynamicFieldValue($fields, 'income_range');

        $this->assertSame('entre $4.500.000 a $6.500.000', $value);
    }

    public function test_resolve_dynamic_field_value_uses_normalized_lookup_for_legacy_key_variants(): void
    {
        $fields = [
            'en que rango se encuentra tu renta liquida' => 'entre $2.500.000 a $3.500.000',
        ];

        $value = $this->invokeResolveDynamicFieldValue($fields, 'rango_renta');

        $this->assertSame('entre $2.500.000 a $3.500.000', $value);
    }

    private function invokeResolveDynamicFieldValue(array $fields, string $fieldKey): mixed
    {
        $reflection = new ReflectionClass(ContactSubmissionsTable::class);
        $method = $reflection->getMethod('resolveDynamicFieldValue');
        $method->setAccessible(true);

        return $method->invoke(null, $fields, $fieldKey);
    }
}
