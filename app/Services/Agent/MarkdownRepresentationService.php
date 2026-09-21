<?php

namespace App\Services\Agent;

use App\Models\Proyecto;
use App\Models\SiteSetting;
use Illuminate\Http\Response;

class MarkdownRepresentationService
{
    /**
     * Generate a rich markdown representation of the site homepage and catalog.
     */
    public function renderHomepageMarkdown(): string
    {
        $settings = SiteSetting::current();
        $siteName = (string) ($settings->site_name ?: 'iLeben');
        $siteDescription = (string) ($settings->site_description ?: 'Plataforma de proyectos inmobiliarios y departamentos en venta en Chile.');
        $siteUrl = rtrim((string) ($settings->site_url ?: config('app.frontend_url', url('/'))), '/');

        $markdown = [];
        $markdown[] = "# {$siteName}";
        $markdown[] = "";
        $markdown[] = "> {$siteDescription}";
        $markdown[] = "";
        $markdown[] = "## Información General";
        $markdown[] = "- **Sitio Web Principal**: {$siteUrl}/";
        $markdown[] = "- **Catálogo de Unidades**: {$siteUrl}/f";
        $markdown[] = "- **Contacto y Asesoría**: {$siteUrl}/contacto";
        $markdown[] = "";

        // Proyectos activos
        try {
            $proyectos = Proyecto::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            if ($proyectos->isNotEmpty()) {
                $markdown[] = "## Proyectos Inmobiliarios Disponibles";
                $markdown[] = "";

                foreach ($proyectos as $proyecto) {
                    $comuna = $proyecto->comuna ?: 'Chile';
                    $region = $proyecto->region ?: '';
                    $location = trim("{$comuna}, {$region}", ', ');
                    $direccion = $proyecto->direccion ? " - {$proyecto->direccion}" : '';
                    $slug = $proyecto->slug;

                    $markdown[] = "### {$proyecto->name}";
                    $markdown[] = "- **Ubicación**: {$location}{$direccion}";
                    if ($proyecto->etapa) {
                        $markdown[] = "- **Etapa**: {$proyecto->etapa}";
                    }
                    if ($slug) {
                        $markdown[] = "- **Ver Unidades**: {$siteUrl}/p/{$slug}";
                    }
                    $markdown[] = "";
                }
            }
        } catch (\Throwable) {
            // Failsafe if DB is temporarily unreachable
        }

        // Contacto
        $contact = $settings->contact ?? [];
        if (!empty($contact)) {
            $markdown[] = "## Canales de Contacto";
            if (!empty($contact['email'])) {
                $markdown[] = "- **Email**: {$contact['email']}";
            }
            if (!empty($contact['phone'])) {
                $markdown[] = "- **Teléfono**: {$contact['phone']}";
            }
            if (!empty($contact['address'])) {
                $markdown[] = "- **Dirección**: {$contact['address']}";
            }
            $markdown[] = "";
        }

        // Recursos para Agentes IA y APIs
        $markdown[] = "## Recursos para Agentes y Desarrolladores";
        $markdown[] = "- **Catálogo de APIs (RFC 9727)**: `{$siteUrl}/.well-known/api-catalog` (Content-Type: `application/linkset+json`)";
        $markdown[] = "- **Especificación OpenAPI v1**: `{$siteUrl}/api/v1` (JSON)";
        $markdown[] = "- **Mapa del Sitio (Sitemap)**: `{$siteUrl}/sitemap.xml`";
        $markdown[] = "- **Archivo LLMs**: `{$siteUrl}/llms.txt`";

        return implode("\n", $markdown) . "\n";
    }

    /**
     * Estimate token count for a markdown string (approx 4 chars per token).
     */
    public function estimateTokens(string $markdown): int
    {
        return max(1, (int) ceil(mb_strlen($markdown) / 4));
    }

    /**
     * Create an HTTP response with text/markdown and standard agent headers.
     */
    public function makeResponse(string $markdown): Response
    {
        $tokens = $this->estimateTokens($markdown);

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Vary' => 'Accept',
            'x-markdown-tokens' => (string) $tokens,
            'Link' => '</.well-known/api-catalog>; rel="api-catalog", </api/v1>; rel="service-desc"; type="application/json", </api/v1>; rel="service-doc"',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        ]);
    }
}
