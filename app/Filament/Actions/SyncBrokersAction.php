<?php

namespace App\Filament\Actions;

use App\Models\Broker;
use App\Services\Salesforce\SalesforceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncBrokersAction
{
    public static function make(): Action
    {
        return Action::make('sync_brokers')
            ->label('Sincronizar Brokers')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->action(function (): void {
                $result = self::execute();

                if (($result['success'] ?? false) === true) {
                    Notification::make()
                        ->title('Sincronización de brokers completada')
                        ->body((string) ($result['message'] ?? 'Sincronización completada.'))
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Error al sincronizar brokers')
                    ->body((string) ($result['message'] ?? 'Ocurrió un error durante la sincronización.'))
                    ->danger()
                    ->send();
            });
    }

    /**
     * Sincronizar brokers desde Salesforce (Broker__c) a la base de datos local.
     *
     * @return array{success: bool, message: string, created: int, updated: int, count: int}
     */
    public static function execute(): array
    {
        try {
            Log::info('Iniciando sincronización de brokers desde Salesforce...');

            $salesforceService = app(SalesforceService::class);
            $brokers = $salesforceService->findBrokers();

            if (empty($brokers)) {
                return [
                    'success' => false,
                    'message' => 'No se encontraron brokers en Salesforce.',
                    'count' => 0,
                    'created' => 0,
                    'updated' => 0,
                ];
            }

            $created = 0;
            $updated = 0;
            $syncedAt = Carbon::now();

            foreach ($brokers as $brokerData) {
                $salesforceId = trim((string) ($brokerData['id'] ?? ''));

                if ($salesforceId === '') {
                    continue;
                }

                $existing = Broker::query()->where('salesforce_id', $salesforceId)->first();

                $data = [
                    'display_name' => self::normalizeUtf8Text($brokerData['name'] ?? null),
                    'contact_email' => self::normalizeUtf8Text($brokerData['email'] ?? null),
                    'contact_phone' => self::normalizeUtf8Text($brokerData['phone'] ?? null),
                    'salesforce_synced_at' => $syncedAt,
                ];

                if ($existing instanceof Broker) {
                    $existing->update($data);
                    $updated++;
                } else {
                    Broker::query()->create(array_merge($data, [
                        'salesforce_id' => $salesforceId,
                        'is_active' => true,
                    ]));
                    $created++;
                }
            }

            $salesforceService->syncBrokerCommercialMetricsFromSnapshots();

            $total = $created + $updated;
            $message = "Brokers sincronizados: {$total} (Creados: {$created}, Actualizados: {$updated})";

            Log::info($message);

            return [
                'success' => true,
                'message' => $message,
                'count' => $total,
                'created' => $created,
                'updated' => $updated,
            ];
        } catch (\Throwable $e) {
            Log::error('Error al sincronizar brokers desde Salesforce: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Error al sincronizar: ' . $e->getMessage(),
                'count' => 0,
                'created' => 0,
                'updated' => 0,
            ];
        }
    }

    public static function getTotalBrokers(): int
    {
        return Broker::count();
    }

    private static function normalizeUtf8Text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (mb_check_encoding($trimmed, 'UTF-8')) {
            return $trimmed;
        }

        foreach (['Windows-1252', 'ISO-8859-1'] as $sourceEncoding) {
            try {
                $converted = mb_convert_encoding($trimmed, 'UTF-8', $sourceEncoding);
            } catch (\ValueError) {
                continue;
            }

            $converted = trim($converted);

            if ($converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        return mb_convert_encoding($trimmed, 'UTF-8', 'UTF-8');
    }
}
