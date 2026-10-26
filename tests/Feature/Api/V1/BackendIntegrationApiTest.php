<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\InitialContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InitialContentSeeder::class);

        $user = User::factory()->create();
        $this->token = $user->createToken('integration-test-token')->plainTextToken;
    }

    public function test_get_page_home_returns_canonical_structure(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/pages/home');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'home')
            ->assertJsonPath('data.title', 'Inicio')
            ->assertJsonStructure([
                'data' => [
                    'slug',
                    'title',
                    'subtitle',
                    'badge',
                    'hero' => ['title', 'subtitle', 'image_url', 'video_url'],
                    'seo' => ['title', 'description', 'og_image'],
                    'sections' => [
                        '*' => [
                            'key',
                            'title',
                            'subtitle',
                            'eyebrow',
                            'type',
                            'sort_order',
                            'is_active',
                            'data',
                        ],
                    ],
                ],
            ]);

        $sections = collect($response->json('data.sections'));
        $this->assertTrue($sections->contains('key', 'diferenciadores'));
        $this->assertTrue($sections->contains('key', 'project_of_month'));
        $this->assertTrue($sections->contains('key', 'videos_obras'));
        $this->assertTrue($sections->contains('key', 'banner_cta'));
    }

    public function test_get_page_nosotros_returns_sections(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/pages/nosotros');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'nosotros')
            ->assertJsonPath('data.title', 'Nosotros');

        $sections = collect($response->json('data.sections'));
        $this->assertTrue($sections->contains('key', 'historia'));
        $this->assertTrue($sections->contains('key', 'proyectos_entregados'));
    }

    public function test_get_page_not_found_returns_404(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/pages/non-existent-page-slug');

        $response->assertNotFound()
            ->assertJson([
                'error' => 'Página no encontrada',
                'code' => 'PAGE_NOT_FOUND',
            ]);
    }

    public function test_get_inn_project_content_returns_full_canonical_structure(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/proyectos/inn/content');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'project_id',
                    'slug',
                    'name',
                    'theme' => [
                        'accent',
                        'accent_dark',
                        'accent_light',
                        'accent_bg',
                        'gradient',
                        'btn_shadow',
                    ],
                    'hero' => [
                        'eyebrow',
                        'title',
                        'subtitle',
                        'location',
                        'video_url',
                        'logo_url',
                    ],
                    'schedule',
                    'announcement' => [
                        'enabled',
                        'id',
                        'title',
                        'message',
                        'button_text',
                    ],
                    'technical_info',
                    'project_slides',
                    'equipment' => [
                        'brands',
                        'slides',
                    ],
                    'overview' => [
                        'title',
                        'description',
                        'collage',
                        'stats',
                    ],
                    'terminaciones' => [
                        'title',
                        'description',
                        'items',
                    ],
                    'virtual_tours' => [
                        'matterport_url',
                        'panoramic_360_url',
                        'video_url',
                    ],
                    'brochure_pdf_url',
                    'designers',
                    'panoramas',
                    'entorno' => [
                        'description',
                        'map_image_url',
                    ],
                    'amenities',
                    'pois',
                    'bottom_gallery',
                ],
            ]);

        $this->assertSame('#d9bc70', $response->json('data.theme.accent'));
        $this->assertCount(5, $response->json('data.amenities'));
        $this->assertCount(3, $response->json('data.pois'));
    }

    public function test_get_testimonials_returns_all_published(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/testimonials');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'project_id',
                        'name',
                        'role',
                        'avatar_url',
                        'rating',
                        'review_text',
                        'review_date_human',
                        'sort_order',
                    ],
                ],
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_get_menus_by_location(): void
    {
        $headerResponse = $this->withToken($this->token)
            ->getJson('/api/v1/menus/header_main');

        $headerResponse->assertOk()
            ->assertJsonPath('data.location', 'header_main')
            ->assertJsonStructure([
                'data' => [
                    'location',
                    'name',
                    'items' => [
                        '*' => [
                            'id',
                            'label',
                            'url',
                            'target',
                            'is_featured',
                            'badge',
                            'sort_order',
                        ],
                    ],
                ],
            ]);

        $footerResponse = $this->withToken($this->token)
            ->getJson('/api/v1/menus/footer_corporate');

        $footerResponse->assertOk()
            ->assertJsonPath('data.location', 'footer_corporate');
    }

    public function test_site_config_includes_company_legal_and_announcement(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/site-config');

        $response->assertOk()
            ->assertJsonStructure([
                'company' => [
                    'razon_social',
                    'rut',
                    'representante_legal',
                    'rut_representante',
                    'address' => [
                        'street',
                        'city',
                    ],
                    'schedule',
                    'phone',
                    'email',
                ],
                'social_links' => [
                    'facebook',
                    'instagram',
                    'linkedin',
                ],
                'legal' => [
                    'disclaimer',
                    'copyright',
                ],
                'announcement' => [
                    'enabled',
                    'id',
                    'title',
                    'message',
                    'button_text',
                ],
            ]);

        $this->assertSame('Inmobiliaria Cenit Limitada', $response->json('company.razon_social'));
        $this->assertSame('76.065.957-6', $response->json('company.rut'));
    }

    public function test_proyectos_endpoint_respects_sort_order_and_includes_details(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/proyectos?include_details=1');

        $response->assertOk();
        $projects = $response->json('data');

        $this->assertNotEmpty($projects);
        $firstProject = $projects[0];
        $this->assertArrayHasKey('sort_order', $firstProject);
        $this->assertArrayHasKey('detail', $firstProject);
    }

    public function test_contact_submission_allows_broker_without_proyecto_or_comuna(): void
    {
        $payload = [
            'channel' => 'broker',
            'fields' => [
                'name' => 'Carlos Corredor',
                'email' => 'carlos@corretajes.cl',
                'phone' => '+56987654321',
                'rut' => '12.345.678-9',
                'mensaje' => 'Postulación Partner Black',
            ],
            'turnstile_token' => 'bypass-token',
        ];

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/contact-submissions', $payload);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('contact_submissions', [
            'email' => 'carlos@corretajes.cl',
        ]);
    }

    public function test_contact_submission_allows_investor_without_proyecto_or_comuna(): void
    {
        $payload = [
            'channel' => 'investor',
            'fields' => [
                'name' => 'Inversionista Juan',
                'email' => 'juan@inversor.cl',
                'phone' => '+56911223344',
                'rut' => '11.222.333-4',
                'mensaje' => 'Interesado en departamentos en verde con pie en cuotas',
            ],
            'turnstile_token' => 'bypass-token',
        ];

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/contact-submissions', $payload);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('contact_submissions', [
            'email' => 'juan@inversor.cl',
        ]);
    }
}
