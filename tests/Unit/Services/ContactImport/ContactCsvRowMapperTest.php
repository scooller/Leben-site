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
            ->mapWithKeys(static fn (array $mapping): array => [
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

    public function test_map_row_normalizes_phone_and_codeudor_values(): void
    {
        $mapper = app(ContactCsvRowMapper::class);

        $row = [
            'Celular' => '+56 9 9490 3805',
            '¿tienes_la_posibilidad_de_complementar_tu_renta?' => 'sí,_puedo_complementar.',
        ];

        $mappings = [
            ['source_column' => 'Celular', 'target_field' => 'phone'],
            ['source_column' => '¿tienes_la_posibilidad_de_complementar_tu_renta?', 'target_field' => 'fields.codeudor'],
        ];

        $mapped = $mapper->mapRow($row, $mappings, true);

        $this->assertSame('56994903805', $mapped['phone']);
        $this->assertSame('56994903805', $mapped['fields']['celular']);
        $this->assertSame('56994903805', $mapped['fields']['telefono']);
        $this->assertSame('56994903805', $mapped['fields']['phone']);
        $this->assertSame('Si', $mapped['fields']['codeudor']);
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
        $this->assertArrayNotHasKey('apellidos', $mapped['fields']);
        $this->assertSame('56982021604', $mapped['fields']['telefono']);
        $this->assertSame('56982021604', $mapped['fields']['phone']);
        $this->assertSame('entre $4.500.000 a $6.500.000', $mapped['fields']['rango_renta']);
        $this->assertArrayNotHasKey('rango_de_renta', $mapped['fields']);
        $this->assertArrayNotHasKey('en_que_rango_se_encuentra_tu_renta_liquida', $mapped['fields']);
        $this->assertSame('Si', $mapped['fields']['codeudor']);
    }

    public function test_map_row_adds_marketing_aliases_for_medio_y_campana(): void
    {
        $mapper = app(ContactCsvRowMapper::class);

        $row = [
            'Medio de llegada' => 'Meta',
            'Nombre de la Campaña' => 'Viveelsur',
        ];

        $mappings = [
            ['source_column' => 'Medio de llegada', 'target_field' => 'fields.medio_llegada'],
            ['source_column' => 'Nombre de la Campaña', 'target_field' => 'fields.campana'],
        ];

        $mapped = $mapper->mapRow($row, $mappings, true);

        $this->assertSame('Meta', $mapped['fields']['medio_llegada']);
        $this->assertArrayNotHasKey('utm_source', $mapped['fields']);
        $this->assertArrayNotHasKey('lead_source', $mapped['fields']);
        $this->assertSame('Viveelsur', $mapped['fields']['campana']);
        $this->assertSame('Viveelsur', $mapped['fields']['utm_campaign']);
    }

    public function test_map_row_keeps_origen_prospecto_separate_from_medio_de_llegada(): void
    {
        $mapper = app(ContactCsvRowMapper::class);

        $row = [
            'Origen del prospecto' => 'Leben | Vive el sur | Edificio Inn | ICON | Brochure | Febrero 2026',
            'Medio de llegada' => 'Meta',
        ];

        $mappings = [
            ['source_column' => 'Origen del prospecto', 'target_field' => 'fields.origen_prospecto'],
            ['source_column' => 'Medio de llegada', 'target_field' => 'fields.medio_llegada'],
        ];

        $mapped = $mapper->mapRow($row, $mappings, true);

        $this->assertSame('Leben | Vive el sur | Edificio Inn | ICON | Brochure | Febrero 2026', $mapped['fields']['origen_prospecto']);
        $this->assertSame('Meta', $mapped['fields']['medio_llegada']);
        $this->assertArrayNotHasKey('utm_source', $mapped['fields']);
        $this->assertArrayNotHasKey('lead_source', $mapped['fields']);
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
        $this->assertArrayNotHasKey('apellidos', $mapped['fields']);
    }
}
