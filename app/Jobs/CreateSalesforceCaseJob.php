<?php

namespace App\Jobs;

use App\Models\ContactSubmission;
use App\Services\Salesforce\SalesforceCaseMapper;
use App\Services\Salesforce\SalesforceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateSalesforceCaseJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public ContactSubmission $submission) {}

    /**
     * Execute the job.
     */
    public function handle(SalesforceService $salesforceService, SalesforceCaseMapper $mapper): void
    {
        $leadEnabled = (bool) config('services.salesforce.lead_enabled', config('services.salesforce.case_enabled', false));

        Log::debug('CreateSalesforceCaseJob: Inicio de ejecución', [
            'contact_submission_id' => $this->submission->id,
            'lead_enabled' => $leadEnabled,
        ]);

        if (! $leadEnabled) {
            Log::warning('CreateSalesforceCaseJob: Lead deshabilitado, se omite envío', [
                'contact_submission_id' => $this->submission->id,
            ]);

            return;
        }

        $submission = $this->submission->fresh();

        if (! $submission) {
            Log::warning('CreateSalesforceCaseJob: Submission no encontrada al refrescar');

            return;
        }

        try {
            // Flujo Case pausado temporalmente:
            // $payload = $mapper->map($submission);
            // $response = $salesforceService->createCase($payload);

            $payload = $mapper->mapLead($submission);
            $response = $salesforceService->createLead($payload);
            $leadId = (string) ($response['id'] ?? $response['Id'] ?? '');

            $submission->update([
                'salesforce_case_id' => $leadId !== '' ? $leadId : null,
                'salesforce_case_error' => null,
            ]);

            Log::debug('CreateSalesforceCaseJob: Lead creado correctamente', [
                'contact_submission_id' => $submission->id,
                'salesforce_lead_id' => $leadId !== '' ? $leadId : null,
                'salesforce_success' => $response['success'] ?? null,
                'salesforce_errors' => $response['errors'] ?? null,
                'salesforce_response' => $response,
            ]);
        } catch (\Throwable $exception) {
            $errorMessage = Str::limit($exception->getMessage(), 65535, '');

            $submission->update([
                'salesforce_case_error' => $errorMessage,
            ]);

            Log::error('CreateSalesforceCaseJob: Error al crear Lead', [
                'contact_submission_id' => $submission->id,
                'error' => $exception->getMessage(),
                ...$this->extractExceptionContext($exception),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractExceptionContext(\Throwable $exception): array
    {
        $context = [
            'exception_class' => $exception::class,
        ];

        if (! method_exists($exception, 'getResponse')) {
            return $context;
        }

        $response = $exception->getResponse();

        if (! $response) {
            return $context;
        }

        $body = (string) $response->getBody();
        $decodedBody = \json_decode($body, true);

        $context['salesforce_http_status'] = $response->getStatusCode();
        $context['salesforce_error_response'] = is_array($decodedBody)
            ? $decodedBody
            : Str::limit($body, 4000, '');

        return $context;
    }
}
