<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Throwable;

class SalesforceOAuthController extends Controller
{
    /**
     * Inicia el flujo OAuth de Salesforce (WebServer).
     * Forrest::authenticate() construye la URL y redirige directamente a Salesforce.
     */
    public function connect(): RedirectResponse
    {
        Log::info('Salesforce OAuth: Iniciando flujo de conexión');

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

            return redirect('/admin/site-settings')
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

            // Persistir los tokens encriptados de Forrest en la DB para recuperación automática
            // ante limpiezas de caché (deploys, restart de Redis, etc.)
            $tokenCacheKey = config('forrest.storage.path', 'forrest_').'token';
            $refreshTokenCacheKey = config('forrest.storage.path', 'forrest_').'refresh_token';

            $tokenBackup = \Illuminate\Support\Facades\Cache::get($tokenCacheKey);
            $refreshTokenBackup = \Illuminate\Support\Facades\Cache::get($refreshTokenCacheKey);

            if ($tokenBackup !== null) {
                data_set($extraSettings, 'salesforce_oauth.token_cache_backup', $tokenBackup);
            }

            if ($refreshTokenBackup !== null) {
                data_set($extraSettings, 'salesforce_oauth.refresh_token_cache_backup', $refreshTokenBackup);
            }

            $siteSettings->update([
                'extra_settings' => $extraSettings,
            ]);

            Log::info('Salesforce OAuth: Autenticación completada exitosamente');

            // Guardar en cache por 5 minutos para que la notificación se muestre una sola vez
            \Illuminate\Support\Facades\Cache::put('salesforce_oauth_just_connected', true, now()->addMinutes(5));

            return redirect('/admin/site-settings');
        } catch (Throwable $e) {
            Log::error('Salesforce OAuth: Error en callback', [
                'error' => $e->getMessage(),
            ]);

            return redirect('/admin/site-settings')
                ->withErrors(['salesforce' => 'Error al procesar autorización: '.$e->getMessage()]);
        }
    }
}
