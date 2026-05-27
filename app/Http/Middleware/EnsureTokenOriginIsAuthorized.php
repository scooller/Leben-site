<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenOriginIsAuthorized
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (blank($request->bearerToken())) {
            return response()->json([
                'message' => 'Token de acceso requerido.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return response()->json([
                'message' => 'Token de acceso inválido o expirado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $authorizedUrl = $this->normalizeUrl($token->authorized_url);

        if (blank($authorizedUrl)) {
            return $next($request);
        }

        $requestOrigin = $this->resolveRequestOrigin($request);

        if (blank($requestOrigin) || ($requestOrigin !== $authorizedUrl)) {
            return response()->json([
                'message' => 'La URL de origen no está autorizada para este token.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function resolveRequestOrigin(Request $request): ?string
    {
        foreach (['Origin', 'Referer', 'X-Authorized-Url'] as $header) {
            $normalized = $this->normalizeUrl($request->headers->get($header));

            if (filled($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $parts = parse_url(trim($url));

        if (! is_array($parts) || blank($parts['scheme'] ?? null) || blank($parts['host'] ?? null)) {
            return null;
        }

        $normalized = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);

        if (filled($parts['port'] ?? null)) {
            $normalized .= ':' . $parts['port'];
        }

        return rtrim($normalized, '/');
    }
}
