<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TurnstileVerificationService
{
    public function verify(string $token, ?string $remoteIp = null): bool
    {
        $secretKey = (string) config('services.turnstile.secret_key', '');

        if ($secretKey === '' || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->connectTimeout(4)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_filter([
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ], static fn (mixed $value): bool => filled($value)));

            if ($response->failed()) {
                Log::warning('Turnstile verification request failed', [
                    'status' => $response->status(),
                ]);

                return false;
            }

            return (bool) $response->json('success', false);
        } catch (Throwable $exception) {
            Log::warning('Turnstile verification exception', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
