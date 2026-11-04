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

        $pickerState = [
            (string) \Illuminate\Support\Str::uuid() => $media->toArray(),
        ];

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.gallery_image_ids', $pickerState)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals([$media->id], $proyecto->fresh()->gallery_image_ids);
    }

    public function test_edit_proyecto_persists_panoramas_and_designers_with_curator_picker(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        $media = \Awcodes\Curator\Models\Media::create([
            'disk' => 'public',
            'directory' => 'media',
            'visibility' => 'public',
            'name' => 'volcan-ensenada',
            'path' => 'media/volcan-ensenada.jpg',
            'width' => 1920,
            'height' => 1080,
            'size' => 204800,
            'type' => 'image/jpeg',
            'ext' => 'jpg',
        ]);

        $panoramasCategories = [
            [
                'id' => 'turismo',
                'name' => 'Turismo',
                'tabLabel' => 'Turismo',
                'slides' => [
                    [
                        'id' => 'ensenada-1',
                        'title' => 'Ensenada',
                        'distance' => '40 km',
                        'image' => null,
                        'media_id' => [(string) \Illuminate\Support\Str::uuid() => $media->toArray()],
                    ],
                ],
            ],
        ];

        $designers = [
            [
                'name' => 'Sofía Iturralde',
                'image' => null,
                'media_id' => [(string) \Illuminate\Support\Str::uuid() => $media->toArray()],
                'color' => '#d9bc70',
                'alt' => 'Sofía',
                'text' => 'Diseño exclusivo',
            ],
        ];

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.panoramas_categories', $panoramasCategories)
            ->set('data.detail.designers', $designers)
            ->call('save')
            ->assertHasNoFormErrors();

        $detail = $proyecto->fresh()->detail;
        $this->assertCount(1, $detail->panoramas_categories);
        $this->assertEquals($media->id, $detail->panoramas_categories[0]['slides'][0]['media_id']);
        $this->assertEquals($media->url, $detail->panoramas_categories[0]['slides'][0]['image']);

        $this->assertCount(1, $detail->designers);
        $this->assertEquals($media->id, $detail->designers[0]['media_id']);
        $this->assertEquals($media->url, $detail->designers[0]['image']);

        $resource = new \App\Http\Resources\Api\ProjectDetailResource($proyecto->fresh());
        $array = $resource->toArray(request());
        $this->assertEquals($media->url, $array['panoramas']['categories'][0]['slides'][0]['image']);
        $this->assertEquals($media->url, $array['designers'][0]['image']);
    }

    public function test_edit_proyecto_persists_project_slides_and_amenities_with_curator_picker(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        $media = \Awcodes\Curator\Models\Media::create([
            'disk' => 'public',
            'directory' => 'media',
            'visibility' => 'public',
            'name' => 'slide-acceso',
            'path' => 'media/slide-acceso.jpg',
            'width' => 1920,
            'height' => 1080,
            'size' => 204800,
            'type' => 'image/jpeg',
            'ext' => 'jpg',
        ]);

        $projectSlides = [
            [
                'alt' => 'Acceso Principal',
                'img' => null,
                'media_id' => [(string) \Illuminate\Support\Str::uuid() => $media->toArray()],
            ],
        ];

        $espaciosItems = [
            [
                'id' => 'gourmet',
                'label' => 'Sala Gourmet',
                'icon_key' => 'chef-hat',
                'alt' => 'Gourmet',
                'link' => null,
                'img' => null,
                'media_id' => [(string) \Illuminate\Support\Str::uuid() => $media->toArray()],
            ],
        ];

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.project_slides', $projectSlides)
            ->set('data.detail.espacios_items', $espaciosItems)
            ->call('save')
            ->assertHasNoFormErrors();

        $detail = $proyecto->fresh()->detail;
        $this->assertCount(1, $detail->project_slides);
        $this->assertEquals($media->id, $detail->project_slides[0]['media_id']);
        $this->assertEquals($media->url, $detail->project_slides[0]['img']);

        $this->assertCount(1, $detail->espacios_items);
        $this->assertEquals($media->id, $detail->espacios_items[0]['media_id']);
        $this->assertEquals($media->url, $detail->espacios_items[0]['img']);
    }
}
