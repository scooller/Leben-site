<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingFrontendConfigTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure brand_color is persisted and exposed in the public site configuration payload.
     */
    public function test_for_frontend_includes_brand_color(): void
    {
        SiteSetting::current()->update([
            'brand_color' => '#112233',
            'evento_sale' => true,
            'mostrar_plantas' => false,
            'contact_page_title' => 'Conversemos',
            'contact_page_subtitle' => 'Te ayudamos a elegir tu próxima planta',
            'contact_page_content' => '<p>Contenido administrable de contacto</p>',
            'contact_form_fields' => [
                [
                    'key' => 'reason',
                    'label' => 'Motivo',
                    'icon' => 'circle-question',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'cotizacion', 'label' => 'Cotización'],
                        ['value' => 'visita', 'label' => 'Agendar visita'],
                    ],
                ],
            ],
            'contact_notification_email' => 'ventas@ileben.cl',
            'tag_manager_id' => 'GTM-TEST123',
            'extra_settings' => [
                'home_hero_type' => 'video',
                'home_hero_video_desktop_url' => 'https://cdn.example.com/home-desktop.mp4',
                'home_hero_video_mobile_url' => 'https://cdn.example.com/home-mobile.mp4',
                'contact_hero_alt' => 'Hero contacto',
                'catalogo_no_disponible_titulo' => 'Volvemos pronto',
                'catalogo_no_disponible_mensaje' => 'Estamos actualizando nuestras plantas. Vuelve en breve.',
                'utm_campaign_default' => 'campaign',
            ],
            'footer_menu' => [
                [
                    'label' => 'Bases Legales',
                    'url' => '/bases-legales',
                    'new_tab' => false,
                ],
            ],
            'footer_legal_text' => '<p>Texto legal de prueba</p>',
        ]);

        $payload = SiteSetting::forFrontend();

        $this->assertArrayHasKey('brand_color', $payload);
        $this->assertSame('#112233', $payload['brand_color']);
        $this->assertArrayHasKey('evento_sale', $payload);
        $this->assertTrue($payload['evento_sale']);
        $this->assertArrayHasKey('mostrar_plantas', $payload);
        $this->assertFalse($payload['mostrar_plantas']);
        $this->assertArrayHasKey('catalogo_no_disponible_titulo', $payload);
        $this->assertSame('Volvemos pronto', $payload['catalogo_no_disponible_titulo']);
        $this->assertArrayHasKey('catalogo_no_disponible_mensaje', $payload);
        $this->assertSame('Estamos actualizando nuestras plantas. Vuelve en breve.', $payload['catalogo_no_disponible_mensaje']);
        $this->assertArrayHasKey('logo_sale', $payload);
        $this->assertNull($payload['logo_sale']);
        $this->assertArrayHasKey('footer_menu', $payload);
        $this->assertSame('Bases Legales', $payload['footer_menu'][0]['label']);
        $this->assertSame('/bases-legales', $payload['footer_menu'][0]['url']);
        $this->assertFalse($payload['footer_menu'][0]['new_tab']);
        $this->assertArrayHasKey('footer_legal_text', $payload);
        $this->assertSame('<p>Texto legal de prueba</p>', $payload['footer_legal_text']);
        $this->assertArrayHasKey('contact_page', $payload);
        $this->assertSame('Conversemos', $payload['contact_page']['title']);
        $this->assertSame('Te ayudamos a elegir tu próxima planta', $payload['contact_page']['subtitle']);
        $this->assertSame('<p>Contenido administrable de contacto</p>', $payload['contact_page']['content']);
        $this->assertArrayHasKey('form_fields', $payload['contact_page']);
        $this->assertSame('reason', $payload['contact_page']['form_fields'][0]['key']);
        $this->assertSame('select', $payload['contact_page']['form_fields'][0]['type']);
        $this->assertSame('circle-question', $payload['contact_page']['form_fields'][0]['icon']);
        $this->assertSame('cotizacion', $payload['contact_page']['form_fields'][0]['options'][0]['value']);
        $this->assertArrayHasKey('seo', $payload);
        $this->assertSame('GTM-TEST123', $payload['seo']['tag_manager_id']);
        $this->assertSame('campaign', $payload['seo']['utm_campaign_default']);
        $this->assertArrayHasKey('hero', $payload);
        $this->assertSame('video', $payload['hero']['home']['type']);
        $this->assertArrayHasKey('image_desktop', $payload['hero']['home']);
        $this->assertArrayHasKey('image_mobile', $payload['hero']['home']);
        $this->assertSame('https://cdn.example.com/home-desktop.mp4', $payload['hero']['home']['video_desktop_url']);
        $this->assertArrayHasKey('image_desktop', $payload['hero']['contact']);
        $this->assertArrayHasKey('image_mobile', $payload['hero']['contact']);
        $this->assertSame('Hero contacto', $payload['hero']['contact']['alt']);
    }
}
