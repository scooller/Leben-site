<?php

namespace Tests\Feature\Feature\Filament;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Resources\Payments\Support\ManualPaymentActionSupport;
use App\Models\Payment;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\PlantReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ManualPaymentActionSupportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{payment: Payment, plant: Plant}
     */
    private function createPendingManualPaymentWithReservation(): array
    {
        $user = User::factory()->create();
        $project = Proyecto::factory()->create();
        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        app(PlantReservationService::class)->reserve($plant->id, $user->id);

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'plant_id' => $plant->id,
            'gateway' => 'manual',
            'gateway_tx_id' => 'MAN-'.Str::upper(Str::ulid()->toBase32()),
            'amount' => 10000,
            'currency' => 'CLP',
            'status' => PaymentStatus::PENDING_APPROVAL,
            'metadata' => [
                'manual_payment_expires_at' => now()->addDay()->toISOString(),
                'manual_payment_proof_path' => 'payment-proofs/proof.pdf',
                'manual_payment_proof_name' => 'proof.pdf',
            ],
        ]);

        return [
            'payment' => $payment,
            'plant' => $plant,
        ];
    }

    public function test_it_approves_manual_payment_and_completes_reservation(): void
    {
        $fixture = $this->createPendingManualPaymentWithReservation();
        $payment = $fixture['payment'];
        $plant = $fixture['plant'];

        Activity::query()->delete();

        $approved = ManualPaymentActionSupport::approve($payment, 777);

        $this->assertTrue($approved);

        $payment->refresh();

        $this->assertSame(PaymentStatus::COMPLETED, $payment->status);
        $this->assertNotNull($payment->completed_at);
        $this->assertSame(777, $payment->metadata['manual_payment_approved_by'] ?? null);
        $this->assertNotNull($payment->metadata['manual_payment_approved_at'] ?? null);

        $reservationStatus = $plant->reservations()->latest('id')->value('status');
        $this->assertSame(ReservationStatus::COMPLETED, $reservationStatus);

        $paymentActivity = Activity::query()
            ->where('log_name', 'payment_workflow')
            ->where('subject_type', Payment::class)
            ->where('subject_id', $payment->getKey())
            ->where('description', 'Pago manual aprobado')
            ->first();

        $reservationActivity = Activity::query()
            ->where('log_name', 'reservation_workflow')
            ->where('description', 'Reserva completada')
            ->first();

        $this->assertNotNull($paymentActivity);
        $this->assertSame(777, $paymentActivity->properties['approved_by']);
        $this->assertSame('manual_payment_approved', $paymentActivity->properties['action']);

        $this->assertNotNull($reservationActivity);
        $this->assertSame('complete_for_plant', $reservationActivity->properties['context']);
    }

    public function test_it_rejects_manual_payment_and_releases_reservation(): void
    {
        $fixture = $this->createPendingManualPaymentWithReservation();
        $payment = $fixture['payment'];
        $plant = $fixture['plant'];

        Activity::query()->delete();

        $rejected = ManualPaymentActionSupport::reject($payment, 'Comprobante ilegible', 888);

        $this->assertTrue($rejected);

        $payment->refresh();

        $this->assertSame(PaymentStatus::FAILED, $payment->status);
        $this->assertSame('Comprobante ilegible', $payment->metadata['manual_payment_rejection_reason'] ?? null);
        $this->assertSame(888, $payment->metadata['manual_payment_rejected_by'] ?? null);
        $this->assertNotNull($payment->metadata['manual_payment_rejected_at'] ?? null);
        $this->assertSame('Comprobante ilegible', $payment->metadata['failure_reason'] ?? null);

        $reservation = $plant->reservations()->latest('id')->first();
        $this->assertNotNull($reservation);
        $this->assertSame(ReservationStatus::RELEASED, $reservation->status);
        $this->assertSame('manual_payment_rejected', $reservation->metadata['release_reason'] ?? null);

        $paymentActivity = Activity::query()
            ->where('log_name', 'payment_workflow')
            ->where('subject_type', Payment::class)
            ->where('subject_id', $payment->getKey())
            ->where('description', 'Pago manual rechazado')
            ->first();

        $reservationActivity = Activity::query()
            ->where('log_name', 'reservation_workflow')
            ->where('description', 'Reserva liberada')
            ->first();

        $this->assertNotNull($paymentActivity);
        $this->assertSame(888, $paymentActivity->properties['rejected_by']);
        $this->assertSame('Comprobante ilegible', $paymentActivity->properties['reason']);

        $this->assertNotNull($reservationActivity);
        $this->assertSame('release_for_plant', $reservationActivity->properties['context']);
        $this->assertSame('manual_payment_rejected', $reservationActivity->properties['reason']);
    }

    public function test_it_resolves_manual_proof_helpers_from_metadata(): void
    {
        $fixture = $this->createPendingManualPaymentWithReservation();
        $payment = $fixture['payment'];

        $this->assertTrue(ManualPaymentActionSupport::hasManualProof($payment));
        $this->assertSame('payment-proofs/proof.pdf', ManualPaymentActionSupport::manualProofPath($payment));
        $this->assertSame('proof.pdf', ManualPaymentActionSupport::manualProofName($payment));
    }
}
