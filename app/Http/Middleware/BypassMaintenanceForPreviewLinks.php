<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;

use Illuminate\Http\Request;

class BypassMaintenanceForPreviewLinks
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next)
    {
        // Si no está en mantenimiento, sigue normal
        $settings = SiteSetting::query()->first();
        if (empty($settings) || !$settings->maintenance_mode) {
            return $next($request);
        }

        // Permitir acceso a previews para bots de link preview y rutas de preview
        $userAgent = strtolower($request->header('User-Agent', ''));
        $isPreviewBot = str_contains($userAgent, 'facebookexternalhit')
            || str_contains($userAgent, 'twitterbot')
            || str_contains($userAgent, 'whatsapp')
            || str_contains($userAgent, 'slackbot')
            || str_contains($userAgent, 'linkedinbot')
            || str_contains($userAgent, 'discordbot')
            || str_contains($userAgent, 'telegrambot')
            || str_contains($userAgent, 'vkshare')
            || str_contains($userAgent, 'pinterest');

        $isPreviewRoute = $request->is('preview-link/*') || $request->is('frontend-preview/*');

        if ($isPreviewBot || $isPreviewRoute) {
            return $next($request);
        }

        // Si no, mostrar página de mantenimiento
        abort(503, $settings->maintenance_message ?? 'Sitio en mantenimiento');
    }
}
