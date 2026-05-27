<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapXmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_xml_includes_static_routes_and_only_indexable_plants(): void
    {
        SiteSetting::current()->update([
            'site_url' => 'https://sale.ileben.cl',
            'mostrar_plantas' => true,
        ]);

        $activeProject = Proyecto::factory()->create([
            'slug' => 'proyecto-activo',
            'is_active' => true,
        ]);

        $inactiveProject = Proyecto::factory()->create([
            'slug' => 'proyecto-inactivo',
            'is_active' => false,
        ]);

        $indexablePlant = Plant::factory()->create([
            'salesforce_proyecto_id' => $activeProject->salesforce_id,
            'name' => 'A-101',
            'is_active' => true,
        ]);

        Plant::factory()->create([
            'salesforce_proyecto_id' => $activeProject->salesforce_id,
            'name' => 'A-102',
            'is_active' => false,
        ]);

        Plant::factory()->create([
            'salesforce_proyecto_id' => $inactiveProject->salesforce_id,
            'name' => 'B-201',
            'is_active' => true,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertStringContainsString('<loc>https://sale.ileben.cl/</loc>', $content);
        $this->assertStringContainsString('<loc>https://sale.ileben.cl/plantas</loc>', $content);
        $this->assertStringContainsString('<loc>https://sale.ileben.cl/f</loc>', $content);
        $this->assertStringContainsString(
            '<loc>https://sale.ileben.cl/p/proyecto-activo/' . rawurlencode((string) $indexablePlant->name) . '</loc>',
            $content
        );

        $this->assertStringNotContainsString('<loc>https://sale.ileben.cl/contacto</loc>', $content);
        $this->assertStringNotContainsString('<loc>https://sale.ileben.cl/pago</loc>', $content);
        $this->assertStringNotContainsString('<loc>https://sale.ileben.cl/p/proyecto-activo/A-102</loc>', $content);
        $this->assertStringNotContainsString('<loc>https://sale.ileben.cl/p/proyecto-inactivo/B-201</loc>', $content);
    }
}
