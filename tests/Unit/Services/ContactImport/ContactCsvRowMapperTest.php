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
        $this->assertSame('fields.apellido', $mapper->suggestTargetField('Apellidos'));
        $this->assertSame('fields.proyecto', $mapper->suggestTargetField('Proyecto:'));
        $this->assertSame('fields.rango_renta', $mapper->suggestTargetField('¿en_qué_rango_se_encuentra_tu_renta_líquida?_'));
        $this->assertSame('fields.codeudor', $mapper->suggestTargetField('¿tienes_la_posibilidad_de_complementar_tu_renta?'));
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

        // Model-level fields should also be mirrored into fields[] using the column signature
        // so that dynamic table columns (fields.nombre, fields.email, etc.) display correctly.
        $this->assertSame('Juan Perez', $mapped['fields']['nombre']);
        $this->assertSame('juan@example.com', $mapped['fields']['email']);
    }

    public function test_map_row_adds_alias_keys_for_common_dynamic_fields(): void
    {
        $mapper = app(ContactCsvRowMapper::class);

        $row = [
            'Nombre' => 'Wendoline',
            'Apellidos' => 'Moreno marmor',
            'Celular' => '56982021604',
            '¿en_qué_rango_se_encuentra_tu_renta_líquida?_' => 'entre_$4.500.000_a_$6.500.000',
            '¿tienes_la_posibilidad_de_complementar_tu_renta?' => 'sí,_puedo_complementar.',
        ];

        $mappings = [
            ['source_column' => 'Nombre', 'target_field' => 'name'],
            ['source_column' => 'Apellidos', 'target_field' => 'fields.apellido'],
            ['source_column' => 'Celular', 'target_field' => 'phone'],
        ];

        $mapped = $mapper->mapRow($row, $mappings, true);

        $this->assertSame('Moreno marmor', $mapped['fields']['apellido']);
        $this->assertSame('Moreno marmor', $mapped['fields']['apellidos']);
        $this->assertSame('56982021604', $mapped['fields']['telefono']);
        $this->assertSame('56982021604', $mapped['fields']['phone']);
        $this->assertSame('entre_$4.500.000_a_$6.500.000', $mapped['fields']['rango_renta']);
        $this->assertSame('sí,_puedo_complementar.', $mapped['fields']['codeudor']);
    }

    public function test_map_row_preserves_source_signature_for_each_explicit_mapping(): void
    {
        $mapper = app(ContactCsvRowMapper::class);

        $row = [
            'Comentario cliente' => 'Me interesa agendar visita',
            'Apellidos' => 'Perez Soto',
        ];

        $mappings = [
            ['source_column' => 'Comentario cliente', 'target_field' => 'fields.mensaje'],
            ['source_column' => 'Apellidos', 'target_field' => 'fields.apellido'],
        ];

        $mapped = $mapper->mapRow($row, $mappings, false);

        $this->assertSame('Me interesa agendar visita', $mapped['fields']['mensaje']);
        $this->assertSame('Me interesa agendar visita', $mapped['fields']['comentario_cliente']);
        $this->assertSame('Perez Soto', $mapped['fields']['apellido']);
        $this->assertSame('Perez Soto', $mapped['fields']['apellidos']);
    }
}
