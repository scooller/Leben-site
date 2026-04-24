<?php

namespace Tests\Feature\Feature\Api;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\FinMail\FinMailNotificationService;
use App\Services\PlantReservationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
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

        $mock = Mockery::mock(FinMailNotificationService::class);
        $mock->shouldReceive('sendManualReservationCreated')
            ->once()
            ->withArgs(function (Payment $payment, $updatedReservation) use ($reservation): bool {
                return $payment->requiresManualApproval()
                    && filled($payment->gateway_tx_id)
                    && (float) $payment->amount > 0
                    && $updatedReservation->id === $reservation->id;
            });
        $this->app->instance(FinMailNotificationService::class, $mock);

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
        $this->assertNotSame($this->user->id, $payment->user_id);
        $this->assertSame('usuario@example.com', $payment->user?->email);
        $this->assertSame($plant->id, $payment->plant_id);
        $this->assertSame($project->id, $payment->project_id);
        $this->assertSame('Usuario Demo', $payment->billing_name);
        $this->assertSame('usuario@example.com', $payment->billing_email);
        $this->assertSame('912345678', $payment->billing_phone);
        $this->assertSame('12345678-5', $payment->billing_rut);
        $this->assertNull(data_get($payment->metadata, 'billing_email'));
        $this->assertNull(data_get($payment->metadata, 'billing_rut'));
        $this->assertStringStartsWith('MAN-', (string) $payment->gateway_tx_id);
        $this->assertSame($payment->gateway_tx_id, $response->json('reference'));

        $reservation->refresh();

        $this->assertSame(ReservationStatus::ACTIVE, $reservation->status);
        $this->assertTrue($reservation->expires_at->greaterThan(now()->addHours(47)));
    }

    public function test_it_accepts_manual_payment_proof_upload(): void
    {
        Storage::fake();

        $admin = User::factory()->create([
            'user_type' => 'admin',
        ]);

        $mock = Mockery::mock(FinMailNotificationService::class);
        $mock->shouldReceive('sendManualPaymentProofSubmittedToAdmins')
            ->once()
            ->withArgs(function (Payment $payment): bool {
                return $payment->status === PaymentStatus::PENDING_APPROVAL;
            });
        $this->app->instance(FinMailNotificationService::class, $mock);

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

        $notification = DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $admin->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString(
            'Comprobante de pago recibido',
            (string) data_get($notification->data, 'title')
        );
    }

    public function test_it_applies_manual_payment_timeout_configured_in_minutes(): void
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
                'auto_expire_minutes' => 30,
                'auto_expire_hours' => 48,
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
            ->assertJsonPath('gateway', 'manual');

        $expiresAt = Carbon::parse((string) $response->json('expires_at'));
        $minutesUntilExpiration = now()->diffInMinutes($expiresAt, false);

        $this->assertGreaterThanOrEqual(29, $minutesUntilExpiration);
        $this->assertLessThanOrEqual(31, $minutesUntilExpiration);

        $reservation->refresh();

        $reservationMinutesUntilExpiration = now()->diffInMinutes($reservation->expires_at, false);
        $this->assertGreaterThanOrEqual(29, $reservationMinutesUntilExpiration);
        $this->assertLessThanOrEqual(31, $reservationMinutesUntilExpiration);
    }

    public function test_it_does_not_persist_manual_payment_proof_when_post_upload_processing_fails(): void
    {
        Storage::fake();

        User::factory()->create([
            'user_type' => 'admin',
        ]);

        $exception = new ModelNotFoundException;
        $exception->setModel(Payment::class, ['12']);

        $mock = Mockery::mock(FinMailNotificationService::class);
        $mock->shouldReceive('sendManualPaymentProofSubmittedToAdmins')
            ->once()
            ->andThrow($exception);
        $this->app->instance(FinMailNotificationService::class, $mock);

        $payment = Payment::query()->create([
            'user_id' => $this->user->id,
            'gateway' => 'manual',
            'gateway_tx_id' => 'MAN-TEST-ATOMIC',
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
            'notes' => 'Transferencia con falla posterior.',
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('message', 'No se pudo registrar el comprobante. Intenta nuevamente.');

        $payment->refresh();

        $this->assertFalse((bool) ($payment->metadata['manual_payment_proof_submitted'] ?? false));
        $this->assertNull($payment->metadata['manual_payment_proof_path'] ?? null);
        $this->assertNull($payment->metadata['manual_payment_proof_name'] ?? null);

        $proofs = Storage::allFiles('payment-proofs');
        $this->assertSame([], $proofs);
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

    public function test_it_reuses_existing_user_by_billing_email_without_updating_profile_and_updates_billing_per_payment(): void
    {
        $existingCustomer = User::factory()->create([
            'email' => 'cliente.existente@example.com',
            'name' => 'Cliente Original',
            'phone' => '900000000',
            'rut' => '11111111-1',
            'user_type' => 'customer',
        ]);

        $project = Proyecto::factory()->create([
            'manual_payment_instructions' => 'Instrucciones manuales',
        ]);

        $firstPlant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        $secondPlant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        SiteSetting::current()->update([
            'gateway_manual_enabled' => true,
            'gateway_manual_config' => [
                'instructions' => 'Instrucciones manuales',
                'auto_expire_hours' => 48,
            ],
        ]);

        $firstReservation = app(PlantReservationService::class)->reserve($firstPlant->id, $this->user->id);

        $firstCheckout = $this->postJson('/api/v1/checkout', [
            'plant_id' => $firstPlant->id,
            'quantity' => 1,
            'gateway' => 'manual',
            'name' => 'Nombre Facturacion Uno',
            'email' => 'cliente.existente@example.com',
            'phone' => '955555551',
            'rut' => '22222222-2',
            'session_token' => $firstReservation->session_token,
        ]);

        $firstCheckout->assertOk();

        $firstPayment = Payment::query()->findOrFail((int) $firstCheckout->json('payment_id'));

        $this->assertSame($existingCustomer->id, $firstPayment->user_id);
        $this->assertSame('Nombre Facturacion Uno', $firstPayment->billing_name);
        $this->assertSame('cliente.existente@example.com', $firstPayment->billing_email);
        $this->assertSame('955555551', $firstPayment->billing_phone);
        $this->assertSame('22222222-2', $firstPayment->billing_rut);

        $secondReservation = app(PlantReservationService::class)->reserve($secondPlant->id, $this->user->id);

        $secondCheckout = $this->postJson('/api/v1/checkout', [
            'plant_id' => $secondPlant->id,
            'quantity' => 1,
            'gateway' => 'manual',
            'name' => 'Nombre Facturacion Dos',
            'email' => 'cliente.existente@example.com',
            'phone' => '955555552',
            'rut' => '33333333-3',
            'session_token' => $secondReservation->session_token,
        ]);

        $secondCheckout->assertOk();

        $secondPayment = Payment::query()->findOrFail((int) $secondCheckout->json('payment_id'));

        $this->assertSame($existingCustomer->id, $secondPayment->user_id);
        $this->assertSame('Nombre Facturacion Dos', $secondPayment->billing_name);
        $this->assertSame('cliente.existente@example.com', $secondPayment->billing_email);
        $this->assertSame('955555552', $secondPayment->billing_phone);
        $this->assertSame('33333333-3', $secondPayment->billing_rut);

        $existingCustomer->refresh();

        $this->assertSame('Cliente Original', $existingCustomer->name);
        $this->assertSame('900000000', $existingCustomer->phone);
        $this->assertSame('11111111-1', $existingCustomer->rut);
        $this->assertSame(1, User::query()->where('email', 'cliente.existente@example.com')->count());
    }
}
