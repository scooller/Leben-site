<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMarketingPanelAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        if (! $user->isMarketing()) {
            abort(403);
        }

        $routeName = (string) $request->route()?->getName();

        if ($this->isAllowedForMarketing($routeName)) {
            return $next($request);
        }

        abort(403);
    }

    private function isAllowedForMarketing(string $routeName): bool
    {
        if ($routeName === '') {
            return $this->isLivewireFromAllowedPage();
        }

        if ($routeName === 'filament.admin.pages.dashboard') {
            return true;
        }

        if ($this->matchesAnyPrefix($routeName, [
            'filament.admin.resources.asesores.asesors.',
            'filament.admin.resources.plants.',
            'filament.admin.resources.proyectos.',
            'filament.admin.resources.payments.',
            'filament.admin.resources.reservations.plant-reservations.',
            'filament.admin.resources.frontend-preview-links.',
        ])) {
            return ! $this->matchesAnySuffix($routeName, ['.create', '.edit']);
        }

        if (str_starts_with($routeName, 'filament.admin.resources.contact-submissions.contact-submissions.')) {
            return $this->matchesAnySuffix($routeName, ['.index', '.view', '.create', '.edit']);
        }

        if (str_starts_with($routeName, 'filament.admin.resources.short-links.')) {
            return $this->matchesAnySuffix($routeName, ['.index', '.view', '.create', '.edit']);
        }

        return false;
    }

    private function isLivewireFromAllowedPage(): bool
    {
        if (! request()->is('livewire/*')) {
            return false;
        }

        $referer = request()->headers->get('referer', '');
        $path = parse_url($referer, PHP_URL_PATH) ?? '';

        $blockedPathPrefixes = [
            '/admin/sent-emails',
            '/admin/templates',
            '/admin/users',
            '/admin/api-tokens',
        ];

        foreach ($blockedPathPrefixes as $blocked) {
            if (str_starts_with($path, $blocked)) {
                return false;
            }
        }

        return str_starts_with($path, '/admin');
    }

    /**
     * @param  array<int, string>  $prefixes
     */
    private function matchesAnyPrefix(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $suffixes
     */
    private function matchesAnySuffix(string $value, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if (str_ends_with($value, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
