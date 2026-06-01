<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
{
	/**
	 * A basic feature test example.
	 */
	use RefreshDatabase;

	public function test_transbank_redirect_accepts_valid_transbank_url(): void
	{
		$response = $this->get('/payments/transbank/redirect?token_ws=token-test&tbk_url=https%3A%2F%2Fwebpay3gint.transbank.cl%2Fwebpayserver%2FinitTransaction');

		$response
			->assertOk()
			->assertSee('Redirigiendo a Transbank', false);
	}

	public function test_transbank_redirect_rejects_invalid_url(): void
	{
		$response = $this->get('/payments/transbank/redirect?token_ws=token-test&tbk_url=https%3A%2F%2Fevil.example%2Fpay');

		$location = (string) $response->headers->get('Location');

		$response
			->assertStatus(302);

		$this->assertStringStartsWith('https://sale.ileben.cl/pago?', $location);
		$this->assertStringContainsString('result=failed', $location);
		$this->assertStringContainsString('error=URL+de+pago+invalida.', $location);
	}

	public function test_transbank_return_without_token_redirects_cancelled_payment_to_frontend(): void
	{
		$statusToken = (string) Str::uuid();

		$payment = Payment::query()->create([
			'gateway' => 'transbank',
			'gateway_tx_id' => 'OPTEST1234',
			'amount' => 99000,
			'currency' => 'CLP',
			'status' => PaymentStatus::PENDING,
			'metadata' => [
				'public_status_token' => $statusToken,
			],
		]);

		$response = $this->post('/payments/transbank/return', [
			'TBK_ORDEN_COMPRA' => 'OPTEST1234',
			'sensitive_field' => 'do-not-persist',
		]);

		$location = (string) $response->headers->get('Location');

		$response->assertStatus(302);

		$this->assertStringStartsWith('https://sale.ileben.cl/pago?', $location);
		$this->assertStringContainsString('result=cancelled', $location);
		$this->assertStringContainsString('payment_id=' . $payment->id, $location);
		$this->assertStringContainsString('status=cancelled', $location);
		$this->assertStringContainsString('status_token=' . $statusToken, $location);

		$payment->refresh();

		$abortPayload = data_get($payment->metadata, 'transbank_abort_payload');

		$this->assertIsArray($abortPayload);
		$this->assertArrayHasKey('tbk_buy_order', $abortPayload);
		$this->assertArrayHasKey('tbk_token', $abortPayload);
		$this->assertArrayNotHasKey('sensitive_field', $abortPayload);
		$this->assertSame('OPTEST1234', (string) ($abortPayload['tbk_buy_order'] ?? ''));
	}
}
