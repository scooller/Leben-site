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
        if (! (bool) config('services.salesforce.case_enabled', false)) {
            return;
        }

        $submission = $this->submission->fresh();

        if (! $submission) {
            return;
        }

        try {
            $payload = $mapper->map($submission);
            $response = $salesforceService->createCase($payload);
            $caseId = (string) ($response['id'] ?? $response['Id'] ?? '');

            $submission->update([
                'salesforce_case_id' => $caseId !== '' ? $caseId : null,
                'salesforce_case_error' => null,
            ]);

            Log::info('CreateSalesforceCaseJob: Case creado correctamente', [
                'contact_submission_id' => $submission->id,
                'salesforce_case_id' => $caseId !== '' ? $caseId : null,
            ]);
        } catch (\Throwable $exception) {
            $errorMessage = Str::limit($exception->getMessage(), 65535, '');

            $submission->update([
                'salesforce_case_error' => $errorMessage,
            ]);

            Log::error('CreateSalesforceCaseJob: Error al crear Case', [
                'contact_submission_id' => $submission->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
