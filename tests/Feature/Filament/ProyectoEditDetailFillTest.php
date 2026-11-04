<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Proyectos\Pages\EditProyecto;
use App\Models\Proyecto;
use App\Models\User;
use Database\Seeders\InitialInnProjectDetailSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProyectoEditDetailFillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_edit_proyecto_fills_all_detail_sections_from_existing_record(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        $livewire = Livewire::test(EditProyecto::class, ['record' => $proyecto->id]);
        $state = $livewire->instance()->form->getRawState();

        $detail = $state['detail'] ?? [];

        $this->assertNotEmpty($detail);
        $this->assertEquals("VIVE EL LUJO\nEN PUERTO VARAS", $detail['hero_title']);
        $this->assertEquals("LOS MEJORES DEPARTAMENTOS\nDE PUERTO VARAS", $detail['project_title']);
        $this->assertEquals("SOFISTICACIÓN\nEN CADA DETALLE", $detail['equipment_title']);
        $this->assertEquals("ESPACIOS DE\nOTRO NIVEL", $detail['espacios_title']);
        $this->assertEquals("ntorno\nINNigualable", $detail['map_title']);
        $this->assertCount(7, $detail['espacios_items']);
        $this->assertCount(4, $detail['panoramas_categories']);
        $this->assertCount(2, $detail['designers']);
    }

    public function test_edit_proyecto_fills_defaults_when_detail_is_empty(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $proyecto = Proyecto::factory()->create([
            'name' => 'Proyecto Nuevo',
            'comuna' => 'Las Condes',
            'direccion' => 'Apoquindo 1234',
        ]);

        $livewire = Livewire::test(EditProyecto::class, ['record' => $proyecto->id]);
        $state = $livewire->instance()->form->getRawState();

        $detail = $state['detail'] ?? [];

        $this->assertEquals('Las Condes', $detail['hero_eyebrow']);
        $this->assertEquals('Proyecto Nuevo', $detail['hero_title']);
    }

    public function test_edit_proyecto_persists_web_info_detail_changes_on_save(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        $livewire = Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.hero_title', 'TITULO ACTUALIZADO HERO')
            ->set('data.detail.project_title', 'PROYECTO ACTUALIZADO')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('project_details', [
            'project_id' => $proyecto->id,
            'hero_title' => 'TITULO ACTUALIZADO HERO',
            'project_title' => 'PROYECTO ACTUALIZADO',
        ]);
    }

    public function test_edit_proyecto_persists_virtual_tours_and_brochure(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        $toursConfig = [
            [
                'label' => 'Masterplan',
                'title' => 'Plano General y Pisos',
                'src' => 'https://example.com/media/masterplan.jpg',
                'interiores' => [
                    [
                        'title' => 'Piso 1 Acceso',
                        'src' => 'https://example.com/media/piso1.jpg',
                    ],
                ],
            ],
            [
                'label' => 'Piloto 360',
                'title' => 'Matterport Virtual',
                'src' => 'https://my.matterport.com/show/?m=sample123',
                'interiores' => [],
            ],
        ];

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.brochure_pdf_url', 'https://example.com/media/brochure.pdf')
            ->set('data.detail.virtual_tours_config', $toursConfig)
            ->call('save')
            ->assertHasNoFormErrors();

        $detail = $proyecto->fresh()->detail;
        $this->assertEquals('https://example.com/media/brochure.pdf', $detail->brochure_pdf_url);
        $this->assertCount(2, $detail->virtual_tours_config);
        $this->assertEquals('https://example.com/media/masterplan.jpg', $detail->virtual_tours_config[0]['src']);
        $this->assertEquals('https://example.com/media/piso1.jpg', $detail->virtual_tours_config[0]['interiores'][0]['src']);
        $this->assertEquals('https://my.matterport.com/show/?m=sample123', $detail->virtual_tours_config[1]['src']);
    }

    public function test_edit_proyecto_persists_curator_media_without_manual_src(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        $media = \Awcodes\Curator\Models\Media::create([
            'disk' => 'public',
            'directory' => 'media',
            'visibility' => 'public',
            'name' => 'masterplan-inn',
            'path' => 'media/masterplan-inn.jpg',
            'width' => 1920,
            'height' => 1080,
            'size' => 257740,
            'type' => 'image/jpeg',
            'ext' => 'jpg',
        ]);

        $toursConfig = [
            [
                'label' => 'Masterplan',
                'title' => 'Masterplan General',
                'src' => null,
                'media_id' => [(string) \Illuminate\Support\Str::uuid() => $media->toArray()],
                'interiores' => [
                    [
                        'title' => 'Masterplan Vista',
                        'src' => null,
                        'media_id' => [(string) \Illuminate\Support\Str::uuid() => $media->toArray()],
                    ],
                ],
            ],
        ];

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.virtual_tours_config', $toursConfig)
            ->call('save')
            ->assertHasNoFormErrors();

        $detail = $proyecto->fresh()->detail;
        $this->assertCount(1, $detail->virtual_tours_config);
        $this->assertEquals($media->id, $detail->virtual_tours_config[0]['media_id']);
        $this->assertEquals($media->url, $detail->virtual_tours_config[0]['src']);
        $this->assertEquals($media->id, $detail->virtual_tours_config[0]['interiores'][0]['media_id']);
        $this->assertEquals($media->url, $detail->virtual_tours_config[0]['interiores'][0]['src']);
    }

    public function test_edit_proyecto_persists_video_interstitials_with_curator_media_without_manual_url(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        $media = \Awcodes\Curator\Models\Media::create([
            'disk' => 'public',
            'directory' => 'media',
            'visibility' => 'public',
            'name' => 'banner-video',
            'path' => 'media/banner-video.mp4',
            'width' => 1920,
            'height' => 1080,
            'size' => 10485760,
            'type' => 'video/mp4',
            'ext' => 'mp4',
        ]);

        $interstitials = [
            [
                'eyebrow' => 'Experiencia INN',
                'title' => 'Vistas Insospechadas',
                'text' => 'Un estilo de vida único frente al lago',
                'video_url' => null,
                'video_media_id' => [(string) \Illuminate\Support\Str::uuid() => $media->toArray()],
            ],
        ];

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.video_interstitials', $interstitials)
            ->call('save')
            ->assertHasNoFormErrors();

        $detail = $proyecto->fresh()->detail;
        $this->assertNotEmpty($detail->video_interstitials);
        $this->assertEquals($media->id, $detail->video_interstitials[0]['video_media_id']);
        $this->assertEquals($media->url, $detail->video_interstitials[0]['video_url']);
    }

    public function test_edit_proyecto_persists_gallery_items(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        $media = \Awcodes\Curator\Models\Media::create([
            'disk' => 'public',
            'directory' => 'media',
            'visibility' => 'public',
            'name' => 'galeria-fachada',
            'path' => 'media/galeria-fachada.jpg',
            'width' => 1920,
            'height' => 1080,
            'size' => 102400,
            'type' => 'image/jpeg',
            'ext' => 'jpg',
        ]);

        $galleries = [
            'item-1' => [
                'title' => 'Fachada Principal',
                'category' => 'exterior',
                'image_id' => [(string) \Illuminate\Support\Str::uuid() => $media->toArray()],
                'image_url' => null,
                'alt_text' => 'Vista frontal del edificio',
                'sort_order' => 1,
            ],
        ];

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.galleries', $galleries)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('project_galleries', [
            'project_detail_id' => $proyecto->detail->id,
            'title' => 'Fachada Principal',
            'category' => 'exterior',
            'image_id' => $media->id,
            'image_url' => $media->url,
            'alt_text' => 'Vista frontal del edificio',
        ]);
    }
}
