<?php

namespace Tests\Feature\Console;

use App\Models\ContactChannel;
use App\Models\ContactSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NormalizeContactRangoRentaKeyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_normalizes_historical_rango_renta_alias_keys(): void
    {
        $channel = ContactChannel::factory()->create();

        $submission = ContactSubmission::query()->create([
            'contact_channel_id' => $channel->id,
            'name' => 'Wendoline',
            'email' => 'wendy@example.com',
            'phone' => '+56 9 8202 1604',
            'fields' => [
                'rango_de_renta' => 'entre_$4.500.000_a_$6.500.000',
                'en_que_rango_se_encuentra_tu_renta_liquida' => 'deprecated_value',
                'codeudor' => 'Si',
            ],
            'submitted_at' => now(),
        ]);

        $this->artisan('contact:normalize-rango-renta-key')
            ->assertExitCode(0);

        $submission->refresh();

        $this->assertSame('entre $4.500.000 a $6.500.000', $submission->fields['rango_renta'] ?? null);
        $this->assertArrayNotHasKey('rango_de_renta', $submission->fields);
        $this->assertArrayNotHasKey('en_que_rango_se_encuentra_tu_renta_liquida', $submission->fields);
        $this->assertSame('Si', $submission->fields['codeudor'] ?? null);
        $this->assertSame('56982021604', $submission->phone);
    }

    public function test_command_dry_run_does_not_persist_changes(): void
    {
        $channel = ContactChannel::factory()->create();

        $submission = ContactSubmission::query()->create([
            'contact_channel_id' => $channel->id,
            'name' => 'Test Dry Run',
            'email' => 'dryrun@example.com',
            'phone' => '+56 9 1111 1111',
            'fields' => [
                'rango_de_renta' => 'entre_$2.500.000_a_$3.500.000',
            ],
            'submitted_at' => now(),
        ]);

        $this->artisan('contact:normalize-rango-renta-key', ['--dry-run' => true])
            ->assertExitCode(0);

        $submission->refresh();

        $this->assertSame('entre_$2.500.000_a_$3.500.000', $submission->fields['rango_de_renta'] ?? null);
        $this->assertArrayNotHasKey('rango_renta', $submission->fields);
        $this->assertSame('56911111111', $submission->phone);
    }
}
