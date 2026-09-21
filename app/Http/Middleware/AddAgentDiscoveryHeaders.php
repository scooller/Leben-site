<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddAgentDiscoveryHeaders
{
    /**
     * Handle an incoming request.
     *
     * Injects RFC 8288 Link headers advertising the API Catalog (RFC 9727)
     * and service description/documentation endpoints for autonomous AI agents.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $discoveryLinks = [
            '</.well-known/api-catalog>; rel="api-catalog"',
            '</api/v1>; rel="service-desc"; type="application/json"',
            '</api/v1>; rel="service-doc"',
        ];

        $currentLink = $response->headers->get('Link');
        $allLinks = $currentLink
            ? array_merge(array_map('trim', explode(',', $currentLink)), $discoveryLinks)
            : $discoveryLinks;

        $response->headers->set('Link', implode(', ', array_unique($allLinks)));

        return $response;
    }
}
