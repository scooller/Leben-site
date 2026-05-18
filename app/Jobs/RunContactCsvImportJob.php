<?php

namespace App\Jobs;

use App\Models\ContactChannel;
use App\Models\ContactSubmission;
use App\Services\ContactImport\ContactCsvRowMapper;
use App\Services\ContactImport\ContactImportProgressTracker;
use App\Services\ContactImport\ContactTextHomologationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Validator;

class RunContactCsvImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array<string, string>>  $rows
     * @param  array<int, array{source_column: string, target_field: string}>  $mappings
     */
    public function __construct(
        public string $importId,
        public array $rows,
        public array $mappings,
        public int $contactChannelId,
        public bool $autoMapUnmapped,
        public bool $homologateComuna,
        public bool $homologateProyecto,
        public bool $syncToSalesforce,
        public bool $dryRun,
        public bool $hasHeader,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}

    public function handle(
        ContactCsvRowMapper $rowMapper,
        ContactTextHomologationService $homologationService,
        ContactImportProgressTracker $tracker,
    ): void {
        $channel = ContactChannel::query()
            ->whereKey($this->contactChannelId)
            ->where('is_active', true)
            ->first();

        if ($channel === null) {
            $tracker->markFailed($this->importId, 'Canal de contacto inválido o inactivo.');

            return;
        }

        $tracker->addLog(
            $this->importId,
            $this->dryRun
                ? 'Inicio de simulación de importación.'
                : 'Inicio de importación.'
        );

        foreach ($this->rows as $lineIndex => $row) {
            $rowNumber = $lineIndex + ($this->hasHeader ? 2 : 1);

            $mapped = $rowMapper->mapRow(
                row: $row,
                mappings: $this->mappings,
                autoMapUnmapped: $this->autoMapUnmapped,
            );

            $fields = $mapped['fields'];

            if ($this->homologateComuna || $this->homologateProyecto) {
                $homologated = $homologationService->homologate(
                    fields: $fields,
                    homologateComuna: $this->homologateComuna,
                    homologateProyecto: $this->homologateProyecto,
                );

                $fields = $homologated['fields'];
                $warningCount = count($homologated['warnings']);

                if ($warningCount > 0) {
                    $tracker->increment($this->importId, 'warnings', $warningCount);
                }
            }

            if (blank($fields['comuna'] ?? null) || blank($fields['proyecto'] ?? null)) {
                $tracker->increment($this->importId, 'failed');
                $tracker->increment($this->importId, 'processed');
                $tracker->addLog($this->importId, "Fila {$rowNumber}: Comuna y Proyecto son obligatorios.");

                continue;
            }

            $email = trim((string) ($mapped['email'] ?? ''));

            if ($email !== '' && Validator::make(['email' => $email], ['email' => ['email']])->fails()) {
                $tracker->increment($this->importId, 'failed');
                $tracker->increment($this->importId, 'processed');
                $tracker->addLog($this->importId, "Fila {$rowNumber}: email inválido.");

                continue;
            }

            $tracker->increment($this->importId, 'created');

            if ($this->dryRun) {
                $tracker->increment($this->importId, 'processed');
                $tracker->addLog($this->importId, "Fila {$rowNumber}: válida para importar.");

                continue;
            }

            $submission = ContactSubmission::query()->create([
                'contact_channel_id' => $channel->id,
                'name' => $mapped['name'],
                'email' => $email !== '' ? $email : null,
                'phone' => filled($mapped['phone'] ?? null) ? $mapped['phone'] : null,
                'rut' => filled($mapped['rut'] ?? null) ? $mapped['rut'] : null,
                'fields' => $fields,
                'recipient_email' => $channel->effectiveNotificationEmail(),
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'submitted_at' => now(),
            ]);

            if ($this->syncToSalesforce) {
                $syncError = rescue(
                    callback: function () use ($submission): null {
                        CreateSalesforceCaseJob::dispatchSync($submission, 'manual');

                        return null;
                    },
                    rescue: static fn (mixed $exception): mixed => $exception,
                    report: false,
                );

                if ($syncError !== null) {
                    $syncErrorMessage = is_string($syncError)
                        ? $syncError
                        : 'Error de sync Salesforce.';

                    $tracker->increment($this->importId, 'sync_failed');
                    $tracker->addLog($this->importId, "Fila {$rowNumber}: error de sync Salesforce - {$syncErrorMessage}");
                } else {
                    $tracker->increment($this->importId, 'synced');
                    $tracker->addLog($this->importId, "Fila {$rowNumber}: sincronizada en Salesforce.");
                }
            }

            $tracker->increment($this->importId, 'processed');
        }

        $tracker->markCompleted($this->importId);
        $tracker->addLog(
            $this->importId,
            $this->dryRun
                ? 'Simulación finalizada. No se crearon contactos ni se ejecutó sync con Salesforce.'
                : 'Importación finalizada.'
        );
    }

    public function failed(mixed $exception): void
    {
        $message = is_string($exception)
            ? $exception
            : 'Fallo inesperado en job de importación.';

        app(ContactImportProgressTracker::class)->markFailed($this->importId, $message);
    }
}
