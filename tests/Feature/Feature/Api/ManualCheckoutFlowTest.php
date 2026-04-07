<?php

namespace Tests\Feature\Feature\Api;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\PlantReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.transbank.environment' => 'integration',
        ]);

        $this->user = User::factory()->create();

        Sanctum::actingAs($this->user);
    }

    public function test_it_lists_manual_gateway_when_enabled(): void
    {
        SiteSetting::current()->update([
            'gateway_manual_enabled' => true,
        ]);

        $response = $this->getJson('/api/v1/payment-gateways');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => 'manual',
                'flow' => 'manual',
            ]);
    }

    public function test_it_does_not_offer_manual_gateway_when_project_has_no_manual_payment_data(): void
    {
        $project = Proyecto::factory()->create([
            'transbank_commerce_code' => '597055555540',
            'manual_payment_instructions' => null,
            'manual_payment_bank_accounts' => null,
            'manual_payment_link' => null,
        ]);

        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        SiteSetting::current()->update([
            'gateway_transbank_enabled' => true,
            'gateway_manual_enabled' => true,
            'gateway_manual_config' => [
                'instructions' => 'Global manual instructions',
            ],
        ]);

        $response = $this->getJson('/api/v1/payment-gateways?plant_id='.$plant->id);

        $response->assertOk()
            ->assertJsonMissing([
                'id' => 'manual',
            ]);
    }

    public function test_it_returns_no_gateways_when_project_has_no_manual_data_and_no_commerce_code(): void
    {
        $project = Proyecto::factory()->create([
            'transbank_commerce_code' => null,
            'manual_payment_instructions' => null,
            'manual_payment_bank_accounts' => null,
            'manual_payment_link' => null,
        ]);

        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        SiteSetting::current()->update([
            'gateway_transbank_enabled' => true,
            'gateway_mercadopago_enabled' => false,
            'gateway_manual_enabled' => true,
            'gateway_manual_config' => [
                'instructions' => 'Global manual instructions',
            ],
        ]);

        $response = $this->getJson('/api/v1/payment-gateways?plant_id='.$plant->id);

        $response->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('gateways', []);
    }

    public function test_it_does_not_offer_transbank_when_only_config_slug_mapping_exists(): void
    {
        config([
            'payments.gateways.transbank.commerce_codes' => [
                'proyecto-sin-codigo-db' => '597099999999',
            ],
        ]);

        $project = Proyecto::factory()->create([
            'name' => 'Proyecto Sin Codigo DB',
            'slug' => 'proyecto-sin-codigo-db',
            'transbank_commerce_code' => null,
            'manual_payment_instructions' => null,
            'manual_payment_bank_accounts' => null,
            'manual_payment_link' => null,
        ]);

        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        SiteSetting::current()->update([
            'gateway_transbank_enabled' => true,
            'gateway_mercadopago_enabled' => false,
            'gateway_manual_enabled' => false,
        ]);

        $response = $this->getJson('/api/v1/payment-gateways?plant_id='.$plant->id);

        $response->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonMissing([
                'id' => 'transbank',
            ]);
    }

    public function test_it_does_not_offer_transbank_when_plant_project_cannot_be_resolved(): void
    {
        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => 'SF-NO-EXISTE',
            'is_active' => true,
        ]);

        SiteSetting::current()->update([
            'gateway_transbank_enabled' => true,
            'gateway_mercadopago_enabled' => false,
            'gateway_manual_enabled' => false,
        ]);

        $response = $this->getJson('/api/v1/payment-gateways?plant_id='.$plant->id);

        $response->assertOk()
            ->assertJsonMissing([
                'id' => 'transbank',
            ]);
    }

    public function test_it_creates_a_manual_payment_with_unique_reference_and_extended_reservation(): void
    {
        $project = Proyecto::factory()->create([
            'manual_payment_instructions' => 'Deposita y comparte tu comprobante.',
        ]);
        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        SiteSetting::current()->update([
            'gateway_manual_enabled' => true,
            'gateway_manual_config' => [
                'instructions' => 'Transfiere y envia tu comprobante.',
                'auto_expire_hours' => 48,
                'bank_accounts' => [
                    [
                        'bank' => 'Banco Demo',
                        'account_type' => 'Cuenta Corriente',
                        'account_number' => '123456789',
                        'account_holder' => 'iLeben SpA',
                    ],
                ],
            ],
        ]);

        $reservation = app(PlantReservationService::class)->reserve($plant->id, $this->user->id);

        $response = $this->postJson('/api/v1/checkout', [
            'plant_id' => $plant->id,
            'quantity' => 1,
            'gateway' => 'manual',
            'name' => 'Usuario Demo',
            'email' => 'usuario@example.com',
            'phone' => '912345678',
            'rut' => '12345678-5',
            'session_token' => $reservation->session_token,
        ]);

        $response->assertOk()
            ->assertJsonPath('flow', 'manual')
            ->assertJsonPath('gateway', 'manual')
            ->assertJsonPath('requires_proof', true);

        $paymentId = $response->json('payment_id');

        $this->assertNotNull($paymentId);

        $payment = Payment::query()->findOrFail($paymentId);

        $gatewayValue = is_object($payment->gateway) ? $payment->gateway->value : (string) $payment->gateway;

        $this->assertSame('manual', $gatewayValue);
        $this->assertSame(PaymentStatus::PENDING_APPROVAL, $payment->status);
        $this->assertSame($plant->id, $payment->plant_id);
        $this->assertSame($project->id, $payment->project_id);
        $this->assertStringStartsWith('MAN-', (string) $payment->gateway_tx_id);
        $this->assertSame($payment->gateway_tx_id, $response->json('reference'));

        $reservation->refresh();

        $this->assertSame(ReservationStatus::ACTIVE, $reservation->status);
        $this->assertTrue($reservation->expires_at->greaterThan(now()->addHours(47)));
    }

    public function test_it_accepts_manual_payment_proof_upload(): void
    {
        Storage::fake();

        $payment = Payment::query()->create([
            'user_id' => $this->user->id,
            'gateway' => 'manual',
            'gateway_tx_id' => 'MAN-TEST-123',
            'amount' => 10000,
            'currency' => 'CLP',
            'status' => PaymentStatus::PENDING_APPROVAL,
            'metadata' => [
                'manual_payment_expires_at' => now()->addDay()->toISOString(),
                'manual_payment_proof_submitted' => false,
            ],
        ]);

        $response = $this->post('/api/v1/payments/'.$payment->id.'/manual-proof', [
            'proof' => UploadedFile::fake()->image('comprobante.jpg'),
            'notes' => 'Transferencia realizada desde banco demo.',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Comprobante recibido correctamente.')
            ->assertJsonPath('payment.metadata.manual_payment_proof_submitted', true);

        $payment->refresh();

        $this->assertNotEmpty($payment->metadata['manual_payment_proof_path'] ?? null);
        $this->assertSame('comprobante.jpg', $payment->metadata['manual_payment_proof_name'] ?? null);
        $this->assertTrue((bool) ($payment->metadata['manual_payment_proof_submitted'] ?? false));
        Storage::assertExists($payment->metadata['manual_payment_proof_path']);
    }

    public function test_it_uses_project_manual_payment_data_with_priority_over_global_settings(): void
    {
        $project = Proyecto::factory()->create([
            'manual_payment_instructions' => 'Deposita al proyecto A y envia comprobante.',
            'manual_payment_bank_accounts' => [
                [
                    'bank' => 'Banco Proyecto',
                    'account_type' => 'Cuenta Corriente',
                    'account_number' => '999001',
                    'account_holder' => 'Proyecto A SpA',
                    'rut' => '76123456-7',
                ],
            ],
            'manual_payment_link' => 'https://pagos.proyecto-a.cl/link',
        ]);

        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        SiteSetting::current()->update([
            'gateway_manual_enabled' => true,
            'gateway_manual_config' => [
                'instructions' => 'Instrucciones globales',
                'bank_accounts' => [
                    [
                        'bank' => 'Banco Global',
                        'account_number' => '123456789',
                    ],
                ],
            ],
        ]);

        $reservation = app(PlantReservationService::class)->reserve($plant->id, $this->user->id);

        $response = $this->postJson('/api/v1/checkout', [
            'plant_id' => $plant->id,
            'quantity' => 1,
            'gateway' => 'manual',
            'name' => 'Usuario Demo',
            'email' => 'usuario@example.com',
            'phone' => '912345678',
            'rut' => '12345678-5',
            'session_token' => $reservation->session_token,
        ]);

        $response->assertOk()
            ->assertJsonPath('instructions', 'Deposita al proyecto A y envia comprobante.')
            ->assertJsonPath('payment_link', 'https://pagos.proyecto-a.cl/link')
            ->assertJsonPath('bank_accounts.0.bank', 'Banco Proyecto');

        $paymentId = $response->json('payment_id');
        $payment = Payment::query()->findOrFail($paymentId);

        $this->assertSame('https://pagos.proyecto-a.cl/link', data_get($payment->metadata, 'manual_payment_link'));
    }

    public function test_it_returns_only_payment_link_when_manual_instructions_and_bank_accounts_are_empty(): void
    {
        $project = Proyecto::factory()->create([
            'manual_payment_instructions' => null,
            'manual_payment_bank_accounts' => null,
            'manual_payment_link' => 'https://pagos.proyecto-link.cl/manual',
        ]);

        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        SiteSetting::current()->update([
            'gateway_manual_enabled' => true,
            'gateway_manual_config' => [
                'instructions' => null,
                'bank_accounts' => [],
                'payment_link' => 'https://pagos.global.cl/no-usar',
            ],
        ]);

        $reservation = app(PlantReservationService::class)->reserve($plant->id, $this->user->id);

        $response = $this->postJson('/api/v1/checkout', [
            'plant_id' => $plant->id,
            'quantity' => 1,
            'gateway' => 'manual',
            'name' => 'Usuario Demo',
            'email' => 'usuario@example.com',
            'phone' => '912345678',
            'rut' => '12345678-5',
            'session_token' => $reservation->session_token,
        ]);

        $response->assertOk()
            ->assertJsonPath('flow', 'manual')
            ->assertJsonPath('payment_link', 'https://pagos.proyecto-link.cl/manual')
            ->assertJsonPath('instructions', null)
            ->assertJsonPath('bank_accounts', []);
    }

    public function test_it_does_not_inherit_manual_bank_accounts_from_default_config(): void
    {
        config([
            'payments.gateways.manual.bank_accounts' => [
                [
                    'bank' => 'Banco Default',
                    'account_number' => '00000000',
                ],
            ],
            'payments.gateways.manual.instructions' => 'Instrucciones default',
        ]);

        $project = Proyecto::factory()->create([
            'manual_payment_instructions' => 'Solo instrucciones del proyecto.',
            'manual_payment_bank_accounts' => null,
            'manual_payment_link' => 'https://pagos.proyecto.cl/link',
        ]);

        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        SiteSetting::current()->update([
            'gateway_manual_enabled' => true,
            'gateway_manual_config' => [],
        ]);

        $reservation = app(PlantReservationService::class)->reserve($plant->id, $this->user->id);

        $response = $this->postJson('/api/v1/checkout', [
            'plant_id' => $plant->id,
            'quantity' => 1,
            'gateway' => 'manual',
            'name' => 'Usuario Demo',
            'email' => 'usuario@example.com',
            'phone' => '912345678',
            'rut' => '12345678-5',
            'session_token' => $reservation->session_token,
        ]);

        $response->assertOk()
            ->assertJsonPath('instructions', 'Solo instrucciones del proyecto.')
            ->assertJsonPath('payment_link', 'https://pagos.proyecto.cl/link')
            ->assertJsonPath('bank_accounts', []);
    }

    public function test_it_rejects_manual_payment_proof_when_deadline_has_expired(): void
    {
        Storage::fake();

        $payment = Payment::query()->create([
            'user_id' => $this->user->id,
            'gateway' => 'manual',
            'gateway_tx_id' => 'MAN-TEST-EXPIRED',
            'amount' => 10000,
            'currency' => 'CLP',
            'status' => PaymentStatus::PENDING_APPROVAL,
            'metadata' => [
                'manual_payment_expires_at' => now()->subHour()->toISOString(),
            ],
        ]);

        $response = $this->post('/api/v1/payments/'.$payment->id.'/manual-proof', [
            'proof' => UploadedFile::fake()->image('comprobante.jpg'),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'La fecha limite para enviar el comprobante ya expiro.');
    }
}
