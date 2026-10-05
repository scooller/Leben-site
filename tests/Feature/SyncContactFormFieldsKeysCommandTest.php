<?php

namespace Tests\Feature;

use App\Models\ContactChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncContactFormFieldsKeysCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_detects_and_fixes_mismatched_keys_in_contact_channel(): void
    {
        $channel = ContactChannel::factory()->create([
            'name' => 'Canal Test',
            'slug' => 'canal-test',
            'form_fields' => [
                [
                    'key' => 'unrelated_key_rut',
                    'label' => 'RUT Cliente',
                    'salesforce_field' => 'RUT__c',
                ],
                [
                    'key' => 'campo_raro_nombre',
                    'label' => 'Tu Nombre',
                    'salesforce_field' => 'FirstName',
                ],
                [
                    'key' => 'campo_sin_sf',
                    'label' => 'Comentario Especial',
                    'salesforce_field' => null,
                ],
            ],
        ]);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('contact:sync-form-fields-keys', ['--dry-run' => true]);
        $this->assertSame(0, $exitCode);

        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('RUT__c', $output);
        $this->assertStringContainsString('unrelated_key_rut', $output);
        $this->assertStringContainsString('rut', $output);

        // En dry run no cambia en DB
        $channel->refresh();
        $this->assertSame('unrelated_key_rut', $channel->form_fields[0]['key']);

        // Ejecutar real
        $this->artisan('contact:sync-form-fields-keys')
            ->assertSuccessful();

        $channel->refresh();
        $this->assertSame('rut', $channel->form_fields[0]['key']);
        $this->assertSame('nombre', $channel->form_fields[1]['key']);
        $this->assertSame('comentario_especial', $channel->form_fields[2]['key']);
    }

    public function test_command_avoids_duplicate_keys_within_same_channel(): void
    {
        $channel = ContactChannel::factory()->create([
            'name' => 'Canal Duplicados',
            'slug' => 'canal-duplicados',
            'form_fields' => [
                [
                    'key' => 'tel_1',
                    'label' => 'Teléfono Fijo',
                    'salesforce_field' => 'Phone',
                ],
                [
                    'key' => 'tel_2',
                    'label' => 'Teléfono Móvil',
                    'salesforce_field' => 'MobilePhone',
                ],
            ],
        ]);

        $this->artisan('contact:sync-form-fields-keys')
            ->assertSuccessful();

        $channel->refresh();
        $this->assertSame('telefono', $channel->form_fields[0]['key']);
        $this->assertSame('telefono_2', $channel->form_fields[1]['key']);
    }
}
