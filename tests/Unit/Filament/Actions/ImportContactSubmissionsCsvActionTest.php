<?php

namespace Tests\Unit\Filament\Actions;

use App\Filament\Actions\ImportContactSubmissionsCsvAction;
use App\Models\SiteSetting;
use Tests\TestCase;

class ImportContactSubmissionsCsvActionTest extends TestCase
{
    public function test_target_field_options_does_not_contain_duplicates_or_redundant_aliases(): void
    {
        $setting = SiteSetting::current();
        $setting->update([
            'contact_form_fields' => [
                ['key' => 'rut', 'label' => 'RUT'],
                ['key' => 'nombre', 'label' => 'Nombre'],
                ['key' => 'apellido', 'label' => 'Apellido'],
                ['key' => 'email', 'label' => 'E-mail'],
                ['key' => 'telefono', 'label' => 'Teléfono'],
                ['key' => 'rango', 'label' => 'Rango de renta'],
                ['key' => 'codeudor', 'label' => 'Codeudor'],
                ['key' => 'comentario_cliente', 'label' => 'Comentario'],
                ['key' => 'buscas', 'label' => 'Buscas tu depto para...'],
                ['key' => 'elaboral', 'label' => 'Estado laboral'],
            ],
        ]);

        $options = ImportContactSubmissionsCsvAction::targetFieldOptions();

        // Must contain base options
        $this->assertArrayHasKey('name', $options);
        $this->assertArrayHasKey('email', $options);
        $this->assertArrayHasKey('phone', $options);
        $this->assertArrayHasKey('rut', $options);
        $this->assertArrayHasKey('fields.comuna', $options);
        $this->assertArrayHasKey('fields.comuna_inversion', $options);
        $this->assertArrayHasKey('fields.proyecto', $options);
        $this->assertArrayHasKey('fields.mensaje', $options);
        $this->assertArrayHasKey('fields.rango_renta', $options);
        $this->assertArrayHasKey('fields.codeudor', $options);

        // Must NOT contain redundant dynamic aliases for primary or base fields
        $this->assertArrayNotHasKey('fields.rut', $options);
        $this->assertArrayNotHasKey('fields.nombre', $options);
        $this->assertArrayNotHasKey('fields.email', $options);
        $this->assertArrayNotHasKey('fields.telefono', $options);
        $this->assertArrayNotHasKey('fields.rango', $options);
        $this->assertArrayNotHasKey('fields.comentario_cliente', $options);

        // Must contain genuine non-duplicated dynamic fields
        $this->assertArrayHasKey('fields.apellido', $options);
        $this->assertSame('Campo dinámico: Apellido', $options['fields.apellido']);
        $this->assertArrayHasKey('fields.buscas', $options);
        $this->assertArrayHasKey('fields.elaboral', $options);
    }
}
