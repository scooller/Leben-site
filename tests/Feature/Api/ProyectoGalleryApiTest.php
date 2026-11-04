<?php

namespace Tests\Feature\Api;

use App\Models\ProjectDetail;
use App\Models\ProjectGallery;
use App\Models\Proyecto;
use App\Models\User;
use Awcodes\Curator\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProyectoGalleryApiTest extends TestCase
{
    use RefreshDatabase;

    private function validAuthHeaders(): array
    {
        $user = User::factory()->create();

        return ['Authorization' => 'Bearer '.$user->createToken('test', ['*'])->plainTextToken];
    }

    public function test_project_show_endpoint_includes_gallery(): void
    {
        $headers = $this->validAuthHeaders();

        $proyecto = Proyecto::factory()->create([
            'name' => 'Edificio Test Galería',
            'slug' => 'edificio-test-galeria',
            'is_active' => true,
        ]);

        $detail = ProjectDetail::create([
            'project_id' => $proyecto->id,
            'hero_title' => 'Test Hero',
        ]);

        ProjectGallery::create([
            'project_detail_id' => $detail->id,
            'title' => 'Foto Fachada',
            'image_url' => 'https://example.com/fachada.jpg',
            'alt_text' => 'Fachada Edificio',
            'category' => 'exterior',
            'sort_order' => 1,
        ]);

        ProjectGallery::create([
            'project_detail_id' => $detail->id,
            'title' => 'Foto Living',
            'image_url' => 'https://example.com/living.jpg',
            'alt_text' => 'Living Depto',
            'category' => 'interior',
            'sort_order' => 2,
        ]);

        $response = $this->getJson("/api/v1/proyectos/{$proyecto->id}", $headers);

        $response->assertOk();
        $response->assertJsonStructure([
            'id',
            'name',
            'gallery' => [
                '*' => ['id', 'category', 'url', 'title', 'alt', 'sort_order'],
            ],
            'galleries',
        ]);

        $data = $response->json();
        $this->assertCount(2, $data['gallery']);
        $this->assertEquals('Foto Fachada', $data['gallery'][0]['title']);
        $this->assertEquals('exterior', $data['gallery'][0]['category']);
        $this->assertEquals('https://example.com/fachada.jpg', $data['gallery'][0]['url']);
    }

    public function test_project_gallery_dedicated_endpoint(): void
    {
        $headers = $this->validAuthHeaders();

        $proyecto = Proyecto::factory()->create([
            'name' => 'Edificio Vista',
            'slug' => 'edificio-vista',
            'is_active' => true,
        ]);

        $detail = ProjectDetail::create([
            'project_id' => $proyecto->id,
        ]);

        ProjectGallery::create([
            'project_detail_id' => $detail->id,
            'title' => 'Piscina Panorámica',
            'image_url' => 'https://example.com/piscina.jpg',
            'category' => 'amenities',
            'sort_order' => 0,
        ]);

        $response = $this->getJson("/api/v1/proyectos/{$proyecto->id}/gallery", $headers);

        $response->assertOk();
        $response->assertJson([
            'project_id' => $proyecto->id,
            'project_name' => 'Edificio Vista',
            'count' => 1,
            'gallery' => [
                [
                    'title' => 'Piscina Panorámica',
                    'category' => 'amenities',
                    'url' => 'https://example.com/piscina.jpg',
                ],
            ],
        ]);
    }

    public function test_project_content_endpoint_includes_gallery_items(): void
    {
        $headers = $this->validAuthHeaders();

        $proyecto = Proyecto::factory()->create([
            'name' => 'Edificio Content Gallery',
            'slug' => 'edificio-content-gallery',
            'is_active' => true,
        ]);

        $detail = ProjectDetail::create([
            'project_id' => $proyecto->id,
        ]);

        ProjectGallery::create([
            'project_detail_id' => $detail->id,
            'title' => 'Avance Obra',
            'image_url' => 'https://example.com/obra.jpg',
            'category' => 'avance',
            'sort_order' => 1,
        ]);

        $response = $this->getJson("/api/v1/proyectos/{$proyecto->slug}/content", $headers);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'gallery',
                'galleries',
                'bottom_gallery',
            ],
        ]);

        $gallery = $response->json('data.gallery');
        $this->assertCount(1, $gallery);
        $this->assertEquals('Avance Obra', $gallery[0]['title']);
    }

    public function test_project_gallery_resolves_curator_media_url_automatically(): void
    {
        $media = Media::create([
            'disk' => 'public',
            'directory' => 'curator',
            'name' => 'test-curator-image',
            'path' => 'curator/test-image.jpg',
            'width' => 800,
            'height' => 600,
            'size' => 12345,
            'type' => 'image/jpeg',
            'ext' => 'jpg',
        ]);

        $proyecto = Proyecto::factory()->create();
        $detail = ProjectDetail::create(['project_id' => $proyecto->id]);

        $gallery = ProjectGallery::create([
            'project_detail_id' => $detail->id,
            'image_id' => $media->id,
            'title' => 'Foto Curator',
        ]);

        $this->assertNotEmpty($gallery->image_url);
        $this->assertEquals($media->url, $gallery->image_url);
        $this->assertEquals($media->url, $gallery->resolved_image_url);
    }
}
