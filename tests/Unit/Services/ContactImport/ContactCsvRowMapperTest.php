<?php

namespace Tests\Unit\Services\ContactImport;

use App\Services\ContactImport\ContactCsvRowMapper;
use Tests\TestCase;

class ContactCsvRowMapperTest extends TestCase
{
    public function test_build_suggested_mappings_returns_expected_targets_for_known_headers(): void
    {
        $mapper = app(ContactCsvRowMapper::class);

        $mappings = $mapper->buildSuggestedMappings([
            'Nombre',
            'email',
            'Celular',
            'COMUNA',
            'Proyecto',
            'Medio de llegada',
        ]);

        $bySource = collect($mappings)
            ->mapWithKeys(static fn(array $mapping): array => [
                (string) $mapping['source_column'] => (string) $mapping['target_field'],
            ])
            ->all();

        $this->assertSame('name', $bySource['Nombre']);
        $this->assertSame('email', $bySource['email']);
        $this->assertSame('phone', $bySource['Celular']);
        $this->assertSame('fields.comuna', $bySource['COMUNA']);
        $this->assertSame('fields.proyecto', $bySource['Proyecto']);
        $this->assertSame('fields.medio_llegada', $bySource['Medio de llegada']);
    }

    public function test_suggest_target_field_uses_expected_aliases(): void
    {
        $mapper = app(ContactCsvRowMapper::class);

        $this->assertSame('name', $mapper->suggestTargetField('Nombre'));
        $this->assertSame('email', $mapper->suggestTargetField('email'));
        $this->assertSame('phone', $mapper->suggestTargetField('Celular'));
        $this->assertSame('fields.proyecto', $mapper->suggestTargetField('Proyecto:'));
        $this->assertSame('fields.origen_prospecto', $mapper->suggestTargetField('Origen del prospecto'));
    }

    public function test_map_row_maps_known_and_unmapped_columns(): void
    {
        $mapper = app(ContactCsvRowMapper::class);

        $row = [
            'Nombre' => 'Juan Perez',
            'email' => 'juan@example.com',
            'Proyecto' => 'ICON',
            'COMUNA' => 'Nunoa',
            'Columna Extra' => 'Valor X',
        ];

        $mappings = [
            ['source_column' => 'Nombre', 'target_field' => 'name'],
            ['source_column' => 'email', 'target_field' => 'email'],
            ['source_column' => 'Proyecto', 'target_field' => 'fields.proyecto'],
            ['source_column' => 'COMUNA', 'target_field' => 'fields.comuna'],
        ];

        $mapped = $mapper->mapRow($row, $mappings, true);

        $this->assertSame('Juan Perez', $mapped['name']);
        $this->assertSame('juan@example.com', $mapped['email']);
        $this->assertSame('ICON', $mapped['fields']['proyecto']);
        $this->assertSame('Nunoa', $mapped['fields']['comuna']);
        $this->assertSame('Valor X', $mapped['fields']['columna_extra']);
    }
}
