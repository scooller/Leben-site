<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class SalesforceOAuthController extends Controller
{
    /**
     * Inicia el flujo OAuth de Salesforce (WebServer).
     * Forrest::authenticate() construye la URL y redirige directamente a Salesforce.
     */
    public function connect(Request $request): RedirectResponse
    {
        Log::info('Salesforce OAuth: Iniciando flujo de conexión');

        $redirectTo = $request->query('redirect_to', $request->headers->get('referer'));

        if (is_string($redirectTo) && $this->isSafeRedirectTarget($redirectTo, $request)) {
            session()->put('salesforce_oauth_redirect_to', $redirectTo);
        }

        // En WebServer flow, authenticate() retorna un RedirectResponse hacia Salesforce
        return Forrest::authenticate();
    }

    /**
     * Callback de Salesforce (después del login).
     * Forrest::callback() intercambia el código por token y lo almacena.
     */
    public function callback(): RedirectResponse
    {
        $error = request('error');
        $errorDescription = request('error_description');

        if ($error) {
            Log::warning('Salesforce OAuth: Error en callback', [
                'error' => $error,
                'error_description' => $errorDescription,
            ]);

            return redirect($this->resolvePostOAuthRedirectTarget())
                ->withErrors(['salesforce' => "Salesforce: {$errorDescription}"]);
        }

        try {
            // Forrest intercambia el código por token y lo guarda en cache
            Forrest::callback();

            $siteSettings = SiteSetting::current();
            $extraSettings = is_array($siteSettings->extra_settings) ? $siteSettings->extra_settings : [];

            data_set($extraSettings, 'salesforce_oauth.connected', true);
            data_set($extraSettings, 'salesforce_oauth.last_connected_at', now()->toIso8601String());
            data_set($extraSettings, 'salesforce_oauth.auth_method', (string) config('forrest.authentication', ''));

            if (auth()->check()) {
                data_set($extraSettings, 'salesforce_oauth.connected_by_user_id', auth()->id());
            }

            $siteSettings->update([
                'extra_settings' => $extraSettings,
            ]);

            Log::info('Salesforce OAuth: Autenticación completada exitosamente');

            // Guardar en cache por 5 minutos para que la notificación se muestre una sola vez
            \Illuminate\Support\Facades\Cache::put('salesforce_oauth_just_connected', true, now()->addMinutes(5));

            return redirect($this->resolvePostOAuthRedirectTarget());
        } catch (\Throwable $e) {
            Log::error('Salesforce OAuth: Error en callback', [
                'error' => $e->getMessage(),
            ]);

            return redirect($this->resolvePostOAuthRedirectTarget())
                ->withErrors(['salesforce' => 'Error al procesar autorización: '.$e->getMessage()]);
        }
    }

    private function resolvePostOAuthRedirectTarget(): string
    {
        $redirectTo = session()->pull('salesforce_oauth_redirect_to');

        if (is_string($redirectTo) && $this->isSafeRedirectTarget($redirectTo, request())) {
            return $redirectTo;
        }

        return '/admin/site-settings';
    }

    private function isSafeRedirectTarget(string $target, Request $request): bool
    {
        if ($target === '' || str_starts_with($target, '//')) {
            return false;
        }

        $targetPath = parse_url($target, PHP_URL_PATH);
        $connectPath = route('salesforce.oauth.connect', [], false);
        $callbackPath = route('salesforce.callback', [], false);

        if (! is_string($targetPath)) {
            return false;
        }

        if ($targetPath === $connectPath || $targetPath === $callbackPath) {
            return false;
        }

        if (str_starts_with($target, '/')) {
            return true;
        }

        $targetHost = parse_url($target, PHP_URL_HOST);

        if (! is_string($targetHost)) {
            return false;
        }

        $allowedHosts = [
            $request->getHost(),
            parse_url((string) config('app.url', ''), PHP_URL_HOST),
        ];

        foreach ($allowedHosts as $allowedHost) {
            if (is_string($allowedHost) && $allowedHost !== '' && strcasecmp($targetHost, $allowedHost) === 0) {
                return true;
            }
        }

        return false;
    }
}
