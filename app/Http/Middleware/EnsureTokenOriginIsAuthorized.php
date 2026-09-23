<?php

namespace App\Http\Middleware;

use App\Models\FrontendPreviewLink;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
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
        if (FrontendPreviewLink::isAuthorizedForRequest($request)) {
            return $next($request);
        }

        if (blank($request->bearerToken())) {
            return response()->json([
                'message' => 'Token de acceso requerido.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = PersonalAccessToken::findToken($request->bearerToken());

        if (! $token instanceof PersonalAccessToken) {
            return response()->json([
                'message' => 'Token de acceso inválido o expirado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return response()->json([
                'message' => 'Token de acceso inválido o expirado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $authorizedUrl = $this->normalizeUrl($token->authorized_url);

        if (blank($authorizedUrl)) {
            return $next($request);
        }

        $requestOrigin = $this->resolveRequestOrigin($request);

        if (blank($requestOrigin) || ! $this->isOriginAuthorized($requestOrigin, $authorizedUrl)) {
            return response()->json([
                'message' => 'La URL de origen no está autorizada para este token.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function isOriginAuthorized(string $requestOrigin, string $authorizedUrl): bool
    {
        if ($requestOrigin === $authorizedUrl) {
            return true;
        }

        $reqParts = parse_url($requestOrigin);
        $authParts = parse_url($authorizedUrl);

        if (! is_array($reqParts) || ! is_array($authParts)) {
            return false;
        }

        $reqHost = strtolower($reqParts['host'] ?? '');
        $authHost = strtolower($authParts['host'] ?? '');

        $loopbackHosts = ['127.0.0.1', 'localhost', '::1', '[::1]'];
        $isReqLoopback = in_array($reqHost, $loopbackHosts, true);
        $isAuthLoopback = in_array($authHost, $loopbackHosts, true);

        if ($isReqLoopback && $isAuthLoopback) {
            $reqPort = (int) ($reqParts['port'] ?? (($reqParts['scheme'] ?? 'http') === 'https' ? 443 : 80));
            $authPort = (int) ($authParts['port'] ?? (($authParts['scheme'] ?? 'http') === 'https' ? 443 : 80));

            return $reqPort === $authPort;
        }

        return false;
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
