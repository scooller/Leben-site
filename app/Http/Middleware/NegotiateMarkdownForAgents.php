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

            // Root homepage or standard public page requests
            if ($path === '' || $path === 'f' || $path === 'plantas' || $path === 'contacto' || $path === 'index.html') {
                $markdown = $this->markdownService->renderHomepageMarkdown();
                return $this->markdownService->makeResponse($markdown);
            }
        }

        $response = $next($request);

        // If response is HTML and the client explicitly requested text/markdown
        if ($wantsMarkdown && str_contains(strtolower((string) $response->headers->get('Content-Type', '')), 'text/html')) {
            $markdown = $this->markdownService->renderHomepageMarkdown();
            return $this->markdownService->makeResponse($markdown);
        }

        return $response;
    }
}
