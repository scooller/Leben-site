<?php

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Payment\TransbankService;
use Tests\TestCase;
use Transbank\Webpay\WebpayPlus;

class TransbankMallTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test 1: Proyecto slug auto-generation
     */
    public function test_proyecto_slug_auto_generation(): void
    {
        $proyecto = Proyecto::factory()->create(['name' => 'Test Project My Unique '.uniqid()]);

        $this->assertStringContainsString('test-project', $proyecto->slug);
        $this->assertNotEmpty($proyecto->slug);
    }

    /**
     * Test 2: Payment project relationship
     */
    public function test_payment_project_relationship(): void
    {
        $proyecto = Proyecto::factory()->create(['name' => 'Payment Test '.uniqid()]);
        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
        ]);

        $payment = Payment::create([
            'user_id' => $this->user->id,
            'project_id' => $proyecto->id,
            'plant_id' => $plant->id,
            'gateway' => PaymentGateway::TRANSBANK,
            'amount' => 50000,
            'currency' => 'CLP',
            'status' => PaymentStatus::PENDING,
        ]);

        $this->assertEquals($proyecto->id, $payment->project->id);
        $this->assertEquals($proyecto->name, $payment->project->name);
        $this->assertEquals($plant->id, $payment->plant->id);
        $this->assertEquals($plant->name, $payment->plant->name);
    }

    /**
     * Test 3: TransbankService instantiation with mall mode
     */
    public function test_transbank_service_mall_mode(): void
    {
        $config = config('payments');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('gateways', $config);

        // Get the Transbank specific config
        $transbankConfig = $config['gateways']['transbank'];

        $this->assertArrayHasKey('mall_mode', $transbankConfig);
        $this->assertArrayHasKey('commerce_codes', $transbankConfig);
        $this->assertIsArray($transbankConfig['commerce_codes']);

        // Service should instantiate without errors
        $service = new \App\Services\Payment\TransbankService($transbankConfig);
        $this->assertIsObject($service);
    }

    /**
     * Test 4: Project transbank_commerce_code resolution
     */
    public function test_proyecto_transbank_commerce_code_resolution(): void
    {
        $proyecto = Proyecto::factory()->create([
            'name' => 'Commerce Test '.uniqid(),
            'transbank_commerce_code' => '597055555540',
        ]);

        // Must prioritize persisted DB value.
        $this->assertSame('597055555540', $proyecto->transbank_commerce_code);

        // If DB value is missing, it should fallback to config by slug.
        config()->set('payments.gateways.transbank.commerce_codes', [
            $proyecto->slug => '597055555541',
        ]);

        $proyecto->update(['transbank_commerce_code' => null]);
        $proyecto->refresh();

        $this->assertSame('597055555541', $proyecto->transbank_commerce_code);
    }

    /**
     * Test 5: Proyecto relationships
     */
    public function test_proyecto_has_payments_relationship(): void
    {
        $proyecto = Proyecto::factory()->create(['name' => 'Payments Test '.uniqid()]);
        $payment = Payment::create([
            'user_id' => $this->user->id,
            'project_id' => $proyecto->id,
            'gateway' => PaymentGateway::TRANSBANK,
            'amount' => 50000,
            'currency' => 'CLP',
            'status' => PaymentStatus::PENDING,
        ]);

        $this->assertTrue($proyecto->payments->contains($payment));
    }

    /**
     * Test 6: Integration mode ignores custom simple credentials
     */
    public function test_integration_mode_ignores_custom_simple_credentials(): void
    {
        $service = new TransbankService([
            'environment' => 'integration',
            'mall_mode' => false,
            'commerce_code' => '999999999999',
            'api_key' => 'custom-api-key',
        ]);

        $resolveCommerceCode = new \ReflectionMethod($service, 'resolveCommerceCode');
        $resolveCommerceCode->setAccessible(true);

        $resolvedCode = $resolveCommerceCode->invoke($service, null);

        $this->assertSame(WebpayPlus::INTEGRATION_COMMERCE_CODE, $resolvedCode);
        $this->assertTrue($service->validateConfiguration());
    }

    /**
     * Test 7: Integration mode ignores custom mall credentials
     */
    public function test_integration_mode_ignores_custom_mall_credentials(): void
    {
        $service = new TransbankService([
            'environment' => 'integration',
            'mall_mode' => true,
            'commerce_code' => '999999999999',
            'api_key' => 'custom-api-key',
        ]);

        $resolveCommerceCode = new \ReflectionMethod($service, 'resolveCommerceCode');
        $resolveCommerceCode->setAccessible(true);

        $resolvedCode = $resolveCommerceCode->invoke($service, null);

        $this->assertSame(WebpayPlus::INTEGRATION_MALL_COMMERCE_CODE, $resolvedCode);
        $this->assertTrue($service->validateConfiguration());
    }
}
