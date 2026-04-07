<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Payment;
use App\Models\PlantReservation;
use App\Models\User;
use BinaryBuilds\CommandRunner\Models\CommandRun;
use Laravel\Sanctum\PersonalAccessToken;

class BusinessActivityLogger
{
    public static function logApiTokenCreated(PersonalAccessToken $token): void
    {
        static::log(
            logName: 'admin_actions',
            subject: $token,
            description: 'Token API creado',
            properties: [
                'action' => 'api_token_created',
                'token_id' => $token->getKey(),
                'token_name' => $token->name,
                'tokenable_id' => $token->tokenable_id,
                'tokenable_type' => $token->tokenable_type,
                'authorized_url' => $token->authorized_url,
                'expires_at' => $token->expires_at?->toISOString(),
            ],
            event: 'created',
        );
    }

    public static function logApiTokenRevoked(PersonalAccessToken $token): void
    {
        static::log(
            logName: 'admin_actions',
            subject: $token,
            description: 'Token API revocado',
            properties: [
                'action' => 'api_token_revoked',
                'token_id' => $token->getKey(),
                'token_name' => $token->name,
                'tokenable_id' => $token->tokenable_id,
                'tokenable_type' => $token->tokenable_type,
            ],
            event: 'deleted',
        );
    }

    public static function logCommandRunnerStarted(CommandRun $commandRun): void
    {
        static::logCommandRunnerEvent(
            commandRun: $commandRun,
            description: 'Command Runner ejecutado',
            action: 'command_runner_started',
            event: 'created',
        );
    }

    public static function logCommandRunnerFinished(CommandRun $commandRun): void
    {
        $action = $commandRun->killed_at ? 'command_runner_killed' : 'command_runner_finished';
        $description = $commandRun->killed_at ? 'Command Runner detenido' : 'Command Runner finalizado';

        static::logCommandRunnerEvent(
            commandRun: $commandRun,
            description: $description,
            action: $action,
        );
    }

    /**
     * @param  array<string, mixed>  $extraProperties
     */
    public static function logMassDeletion(string $resource, int $deletedCount, array $extraProperties = []): void
    {
        static::logWithoutSubject(
            logName: 'admin_actions',
            description: sprintf('Eliminacion masiva de %s', $resource),
            properties: array_merge([
                'action' => 'mass_delete',
                'resource' => $resource,
                'deleted_count' => $deletedCount,
            ], $extraProperties),
            event: 'deleted',
        );
    }

    public static function logManualPaymentApproved(Payment $payment, int|string|null $approvedBy = null): void
    {
        static::log(
            logName: 'payment_workflow',
            subject: $payment,
            description: 'Pago manual aprobado',
            properties: [
                'action' => 'manual_payment_approved',
                'payment_id' => $payment->getKey(),
                'plant_id' => $payment->plant_id,
                'project_id' => $payment->project_id,
                'approved_by' => $approvedBy,
                'gateway_tx_id' => $payment->gateway_tx_id,
            ],
        );
    }

    public static function logManualPaymentRejected(Payment $payment, ?string $reason = null, int|string|null $rejectedBy = null): void
    {
        static::log(
            logName: 'payment_workflow',
            subject: $payment,
            description: 'Pago manual rechazado',
            properties: [
                'action' => 'manual_payment_rejected',
                'payment_id' => $payment->getKey(),
                'plant_id' => $payment->plant_id,
                'project_id' => $payment->project_id,
                'rejected_by' => $rejectedBy,
                'reason' => $reason,
                'gateway_tx_id' => $payment->gateway_tx_id,
            ],
        );
    }

    public static function logReservationReleased(PlantReservation $reservation, string $releasedBy, ?string $reason = null, string $context = 'reservation_released'): void
    {
        static::log(
            logName: 'reservation_workflow',
            subject: $reservation,
            description: 'Reserva liberada',
            properties: [
                'action' => 'reservation_released',
                'context' => $context,
                'reservation_id' => $reservation->getKey(),
                'plant_id' => $reservation->plant_id,
                'user_id' => $reservation->user_id,
                'released_by' => $releasedBy,
                'reason' => $reason,
            ],
        );
    }

    public static function logReservationCompleted(PlantReservation $reservation, string $context = 'reservation_completed'): void
    {
        static::log(
            logName: 'reservation_workflow',
            subject: $reservation,
            description: 'Reserva completada',
            properties: [
                'action' => 'reservation_completed',
                'context' => $context,
                'reservation_id' => $reservation->getKey(),
                'plant_id' => $reservation->plant_id,
                'user_id' => $reservation->user_id,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private static function log(string $logName, object $subject, string $description, array $properties, string $event = 'updated'): void
    {
        $logger = activity($logName)->performedOn($subject);

        if (auth()->check()) {
            $logger->causedBy(auth()->user());
        }

        $logger
            ->event($event)
            ->withProperties($properties)
            ->log($description);
    }

    private static function logCommandRunnerEvent(CommandRun $commandRun, string $description, string $action, string $event = 'updated'): void
    {
        $logger = activity('admin_actions')->performedOn($commandRun);

        if ($commandRun->ran_by) {
            $runner = User::query()->find((int) $commandRun->ran_by);

            if ($runner) {
                $logger->causedBy($runner);
            }
        } elseif (auth()->check()) {
            $logger->causedBy(auth()->user());
        }

        $logger
            ->event($event)
            ->withProperties([
                'action' => $action,
                'command_run_id' => $commandRun->getKey(),
                'command' => $commandRun->command,
                'process_id' => $commandRun->process_id,
                'ran_by' => $commandRun->ran_by,
                'started_at' => $commandRun->started_at,
                'completed_at' => $commandRun->completed_at,
                'killed_at' => $commandRun->killed_at,
                'exit_code' => $commandRun->exit_code,
            ])
            ->log($description);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private static function logWithoutSubject(string $logName, string $description, array $properties, string $event = 'updated'): void
    {
        $logger = activity($logName);

        if (auth()->check()) {
            $logger->causedBy(auth()->user());
        }

        $logger
            ->event($event)
            ->withProperties($properties)
            ->log($description);
    }
}
