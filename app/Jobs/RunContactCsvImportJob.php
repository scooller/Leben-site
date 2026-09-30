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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

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
        try {
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

                try {
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

                    $email = trim((string) ($mapped['email'] ?? ''));

                    if (blank($fields['comuna'] ?? null) || blank($fields['proyecto'] ?? null)) {
                        $missingField = blank($fields['comuna'] ?? null) && blank($fields['proyecto'] ?? null)
                            ? 'Comuna y Proyecto son obligatorios.'
                            : (blank($fields['comuna'] ?? null) ? 'Comuna es obligatoria.' : 'Proyecto es obligatorio.');

                        $tracker->increment($this->importId, 'failed');
                        $tracker->increment($this->importId, 'processed');
                        $tracker->recordFailedRow($this->importId, $rowNumber, $missingField, [
                            'name' => (string) ($mapped['name'] ?? ''),
                            'email' => $email,
                            'phone' => (string) ($mapped['phone'] ?? ''),
                            'comuna' => (string) ($fields['comuna'] ?? ''),
                            'proyecto' => (string) ($fields['proyecto'] ?? ''),
                        ]);
                        $tracker->addLog($this->importId, $this->buildRowSummary(
                            rowNumber: $rowNumber,
                            prefix: 'Comuna y Proyecto son obligatorios.',
                            mapped: $mapped,
                            email: $email,
                            fields: $fields,
                        ));

                        continue;
                    }

                    if ($email !== '' && Validator::make(['email' => $email], ['email' => ['email']])->fails()) {
                        $tracker->increment($this->importId, 'failed');
                        $tracker->increment($this->importId, 'processed');
                        $tracker->recordFailedRow($this->importId, $rowNumber, 'Email inválido: ' . $email, [
                            'name' => (string) ($mapped['name'] ?? ''),
                            'email' => $email,
                            'phone' => (string) ($mapped['phone'] ?? ''),
                            'comuna' => (string) ($fields['comuna'] ?? ''),
                            'proyecto' => (string) ($fields['proyecto'] ?? ''),
                        ]);
                        $tracker->addLog($this->importId, $this->buildRowSummary(
                            rowNumber: $rowNumber,
                            prefix: 'email inválido.',
                            mapped: $mapped,
                            email: $email,
                            fields: $fields,
                        ));

                        continue;
                    }

                    $tracker->increment($this->importId, 'created');

                    if ($this->dryRun) {
                        $tracker->increment($this->importId, 'processed');
                        $tracker->addLog($this->importId, $this->buildRowSummary(
                            rowNumber: $rowNumber,
                            prefix: 'válida para importar.',
                            mapped: $mapped,
                            email: $email,
                            fields: $fields,
                        ));

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

                    $tracker->addLog($this->importId, $this->buildRowSummary(
                        rowNumber: $rowNumber,
                        prefix: 'contacto creado.',
                        mapped: $mapped,
                        email: $email,
                        fields: $fields,
                    ));

                    if ($this->syncToSalesforce) {
                        $syncError = $this->dispatchSalesforceSync($submission);

                        if ($syncError !== null) {
                            $syncErrorMessage = $syncError instanceof Throwable
                                ? $syncError->getMessage()
                                : (is_string($syncError) ? $syncError : 'Error de sync Salesforce.');

                            $tracker->increment($this->importId, 'sync_failed');
                            $tracker->addLog($this->importId, $this->buildRowSummary(
                                rowNumber: $rowNumber,
                                prefix: 'error de sync Salesforce - ' . $syncErrorMessage,
                                mapped: $mapped,
                                email: $email,
                                fields: $fields,
                            ));
                        } else {
                            $tracker->increment($this->importId, 'synced');
                            $tracker->addLog($this->importId, "Fila {$rowNumber}: sincronizada en Salesforce.");
                        }
                    }

                    $tracker->increment($this->importId, 'processed');
                } catch (Throwable $rowError) {
                    Log::warning('Error procesando fila en importación de contactos', [
                        'import_id' => $this->importId,
                        'row' => $rowNumber,
                        'error' => $rowError->getMessage(),
                    ]);

                    $tracker->increment($this->importId, 'failed');
                    $tracker->increment($this->importId, 'processed');
                    $tracker->recordFailedRow(
                        $this->importId,
                        $rowNumber,
                        'Error inesperado: ' . $rowError->getMessage(),
                        [
                            'name' => (string) ($mapped['name'] ?? ''),
                            'email' => (string) ($mapped['email'] ?? ''),
                            'phone' => (string) ($mapped['phone'] ?? ''),
                        ]
                    );
                    $tracker->addLog(
                        $this->importId,
                        "Fila {$rowNumber}: Error al procesar fila - " . $this->displayValue($rowError->getMessage())
                    );
                }
            }

            $tracker->markCompleted($this->importId);
            $tracker->addLog(
                $this->importId,
                $this->dryRun
                    ? 'Simulación finalizada. No se crearon contactos ni se ejecutó sync con Salesforce.'
                    : 'Importación finalizada.'
            );
        } catch (Throwable $jobError) {
            Log::error('Fallo crítico en RunContactCsvImportJob', [
                'import_id' => $this->importId,
                'error' => $jobError->getMessage(),
                'trace' => $jobError->getTraceAsString(),
            ]);

            $tracker->markFailed($this->importId, 'Error crítico en importación: ' . $jobError->getMessage());
            throw $jobError;
        }
    }

    public function failed(mixed $exception): void
    {
        $message = is_string($exception)
            ? $exception
            : ($exception instanceof Throwable ? $exception->getMessage() : 'Fallo inesperado en job de importación.');

        app(ContactImportProgressTracker::class)->markFailed($this->importId, $this->displayValue($message));
    }

    private function displayValue(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        return $text !== '' ? $text : '-';
    }

    protected function dispatchSalesforceSync(ContactSubmission $submission): mixed
    {
        return rescue(
            callback: function () use ($submission): null {
                CreateSalesforceCaseJob::dispatchSync($submission, 'manual');

                return null;
            },
            rescue: static fn(mixed $exception): mixed => $exception,
            report: false,
        );
    }

    /**
     * @param  array{name: string|null, email: string|null, phone: string|null, rut: string|null, fields: array<string, string>}  $mapped
     * @param  array<string, string>  $fields
     */
    private function buildRowSummary(int $rowNumber, string $prefix, array $mapped, string $email, array $fields): string
    {
        return sprintf(
            'Fila %d: %s Nombre: %s | Email: %s | Teléfono: %s | Proyecto: %s | Comuna: %s',
            $rowNumber,
            $prefix,
            $this->displayValue($mapped['name'] ?? null),
            $this->displayValue($email),
            $this->displayValue($mapped['phone'] ?? null),
            $this->displayValue($fields['proyecto'] ?? null),
            $this->displayValue($fields['comuna'] ?? null),
        );
    }
}
