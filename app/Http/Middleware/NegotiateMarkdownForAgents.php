<?php

namespace App\Http\Middleware;

use App\Services\Agent\MarkdownRepresentationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NegotiateMarkdownForAgents
{
    public function __construct(
        protected MarkdownRepresentationService $markdownService
    ) {}

    /**
     * Handle an incoming request.
     *
     * Enables Markdown Content Negotiation for AI agents (Accept: text/markdown).
     * Returns a markdown representation of the requested page with Content-Type: text/markdown
     * and x-markdown-tokens, while keeping HTML as the default for browser traffic.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $acceptHeader = strtolower((string) $request->header('Accept', ''));
        $wantsMarkdown = str_contains($acceptHeader, 'text/markdown');

        if ($wantsMarkdown) {
            $path = trim($request->path(), '/');

            // Allow dedicated markdown routes (like auth.md) to serve their own content
            if ($path === 'auth.md') {
                return $next($request);
            }

            // If non-API request, return markdown representation immediately
            if (! $request->is('api/*') && ! $request->is('payments/*')) {
                $markdown = $this->markdownService->renderHomepageMarkdown();
                return $this->markdownService->makeResponse($markdown);
            }
        }

        $response = $next($request);

        // Fallback: If downstream response is HTML, redirect, or error, and client wants markdown
        if ($wantsMarkdown && ! $request->is('api/*') && ! $request->is('payments/*')) {
            $markdown = $this->markdownService->renderHomepageMarkdown();
            return $this->markdownService->makeResponse($markdown);
        }

        return $response;
    }
}
