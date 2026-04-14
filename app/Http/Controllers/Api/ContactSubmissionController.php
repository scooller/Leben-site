<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactSubmissionRequest;
use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use App\Services\FinMail\FinMailNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ContactSubmissionController extends Controller
{
    public function store(StoreContactSubmissionRequest $request): JsonResponse
    {
        $settings = SiteSetting::current();
        $fields = $request->validated('fields', []);

        $name = $this->fieldValue($fields, ['name', 'nombre']);
        $email = $this->fieldValue($fields, ['email', 'correo']);
        $phone = $this->fieldValue($fields, ['phone', 'telefono', 'celular']);
        $rut = $this->fieldValue($fields, ['rut']);

        $recipientEmail = $settings->contact_notification_email ?: $settings->contact_email;

        $submission = ContactSubmission::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'rut' => $rut,
            'fields' => $fields,
            'recipient_email' => $recipientEmail,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 65535, ''),
            'submitted_at' => now(),
        ]);

        if (filled($recipientEmail)) {
            app(FinMailNotificationService::class)->sendContactSubmissionReceivedToAdmin($submission);
        }

        return response()->json([
            'message' => 'Tu mensaje fue enviado correctamente.',
            'id' => $submission->id,
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<int, string>  $aliases
     */
    private function fieldValue(array $fields, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (! array_key_exists($alias, $fields)) {
                continue;
            }

            $value = trim((string) $fields[$alias]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
