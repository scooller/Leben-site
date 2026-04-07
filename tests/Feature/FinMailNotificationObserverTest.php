<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\FinMail\FinMailNotificationService;
use App\Services\PlantReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FinMailNotificationObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_fin_mail_notification_when_a_reservation_is_created(): void
    {
        $user = User::factory()->create();
        $project = Proyecto::factory()->create();
        $plant = Plant::factory()->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'is_active' => true,
        ]);

        $mock = Mockery::mock(FinMailNotificationService::class);
        $mock->shouldReceive('sendUnitReservationCreated')->once();
        $this->app->instance(FinMailNotificationService::class, $mock);

        app(PlantReservationService::class)->reserve($plant->id, $user->id);
    }

    public function test_it_sends_fin_mail_notification_when_payment_status_changes(): void
    {
        $user = User::factory()->create();

        $payment = Payment::create([
            'user_id' => $user->id,
            'gateway' => 'transbank',
            'amount' => 100000,
            'currency' => 'CLP',
            'status' => PaymentStatus::PENDING,
            'metadata' => [],
        ]);

        $mock = Mockery::mock(FinMailNotificationService::class);
        $mock->shouldReceive('sendPaymentStatusChanged')
            ->once()
            ->withArgs(function (Payment $updatedPayment, string $previousStatus): bool {
                return $updatedPayment->status === PaymentStatus::COMPLETED
                    && $previousStatus === PaymentStatus::PENDING->value;
            });
        $this->app->instance(FinMailNotificationService::class, $mock);

        $payment->update([
            'status' => PaymentStatus::COMPLETED,
        ]);
    }

    public function test_it_does_not_send_fin_mail_notification_for_intermediate_payment_statuses(): void
    {
        $user = User::factory()->create();

        $payment = Payment::create([
            'user_id' => $user->id,
            'gateway' => 'transbank',
            'amount' => 100000,
            'currency' => 'CLP',
            'status' => PaymentStatus::PENDING,
            'metadata' => [],
        ]);

        $mock = Mockery::mock(FinMailNotificationService::class);
        $mock->shouldNotReceive('sendPaymentStatusChanged');
        $this->app->instance(FinMailNotificationService::class, $mock);

        $payment->update([
            'status' => PaymentStatus::PROCESSING,
        ]);
    }

    public function test_it_respects_configured_statuses_for_payment_notifications(): void
    {
        config()->set('payments.notifications.fin_mail.enabled', true);
        config()->set('payments.notifications.fin_mail.on_statuses', [PaymentStatus::FAILED->value]);

        $user = User::factory()->create();

        $payment = Payment::create([
            'user_id' => $user->id,
            'gateway' => 'transbank',
            'amount' => 100000,
            'currency' => 'CLP',
            'status' => PaymentStatus::PENDING,
            'metadata' => [],
        ]);

        $mock = Mockery::mock(FinMailNotificationService::class);
        $mock->shouldNotReceive('sendPaymentStatusChanged');
        $this->app->instance(FinMailNotificationService::class, $mock);

        $payment->update([
            'status' => PaymentStatus::COMPLETED,
        ]);
    }
}
