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

        $proyectoVideo = [
            'eyebrow' => 'Experiencia INN',
            'title' => 'Vistas Insospechadas',
            'text' => 'Un estilo de vida único frente al lago',
            'video_url' => null,
            'video_media_id' => [(string) \Illuminate\Support\Str::uuid() => $media->toArray()],
        ];

        $equipamientoVideo = [
            'eyebrow' => 'Equipamiento Premium',
            'title' => 'Cocina Franke',
            'text' => 'Terminaciones de alto estándar',
            'video_url' => null,
            'video_media_id' => [(string) \Illuminate\Support\Str::uuid() => $media->toArray()],
        ];

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.proyecto_video', $proyectoVideo)
            ->set('data.detail.equipamiento_video', $equipamientoVideo)
            ->call('save')
            ->assertHasNoFormErrors();

        $detail = $proyecto->fresh()->detail;
        $this->assertEquals($media->id, $detail->proyecto_video['video_media_id']);
        $this->assertEquals($media->url, $detail->proyecto_video['video_url']);
        $this->assertEquals($media->id, $detail->equipamiento_video['video_media_id']);
        $this->assertEquals($media->url, $detail->equipamiento_video['video_url']);
        $this->assertEquals($media->url, $detail->video_interstitials[0]['video_url']);
        $this->assertEquals($media->url, $detail->video_interstitials[1]['video_url']);
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

    public function test_virtual_tours_title_is_optional(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        $toursConfig = [
            [
                'label' => 'Masterplan',
                'title' => null,
                'src' => 'https://example.com/tour.jpg',
                'interiores' => [],
            ],
        ];

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.virtual_tours_config', $toursConfig)
            ->call('save')
            ->assertHasNoFormErrors();

        $detail = $proyecto->fresh()->detail;
        $this->assertCount(1, $detail->virtual_tours_config);
        $this->assertEquals('Masterplan', $detail->virtual_tours_config[0]['label']);
        $this->assertNull($detail->virtual_tours_config[0]['title']);
    }

    public function test_edit_proyecto_title_includes_project_name(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        $component = Livewire::test(EditProyecto::class, ['record' => $proyecto->id]);

        $this->assertEquals("Editar proyecto - {$proyecto->name}", $component->instance()->getTitle());
    }

    public function test_edit_proyecto_save_action_requires_confirmation(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        $component = Livewire::test(EditProyecto::class, ['record' => $proyecto->id]);

        $reflection = new \ReflectionMethod(EditProyecto::class, 'getSaveFormAction');
        $reflection->setAccessible(true);
        /** @var \Filament\Actions\Action $saveAction */
        $saveAction = $reflection->invoke($component->instance());

        $this->assertNotNull($saveAction);
        $this->assertTrue($saveAction->isConfirmationRequired());
        $this->assertEquals('¿Guardar cambios del proyecto?', $saveAction->getModalHeading());
        $this->assertEquals('Aceptar', $saveAction->getModalSubmitActionLabel());
        $this->assertEquals('Cancelar', $saveAction->getModalCancelActionLabel());
    }

    public function test_edit_proyecto_header_actions_include_save_and_view_web(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        $component = Livewire::test(EditProyecto::class, ['record' => $proyecto->id]);

        $reflection = new \ReflectionMethod(EditProyecto::class, 'getHeaderActions');
        $reflection->setAccessible(true);
        /** @var array<\Filament\Actions\Action> $headerActions */
        $headerActions = $reflection->invoke($component->instance());

        $saveHeader = collect($headerActions)->first(fn ($a) => $a->getName() === 'saveHeader');
        $this->assertNotNull($saveHeader);
        $this->assertEquals('Guardar', $saveHeader->getLabel());
        $this->assertTrue($saveHeader->isConfirmationRequired());

        $viewWeb = collect($headerActions)->first(fn ($a) => $a->getName() === 'viewWeb');
        $this->assertNotNull($viewWeb);
        $this->assertEquals('Ver Web', $viewWeb->getLabel());
        $this->assertTrue($viewWeb->shouldOpenUrlInNewTab());
        $this->assertStringContainsString('/proyectos/edificio-inn', $viewWeb->getUrl());
    }

    public function test_edit_proyecto_persists_map_bg_color_and_interiorismo_name_position(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.map_bg_color', '#112233')
            ->set('data.detail.interiorismo_name_position', 'above')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('project_details', [
            'project_id' => $proyecto->id,
            'map_bg_color' => '#112233',
            'interiorismo_name_position' => 'above',
        ]);

        $resource = (new \App\Http\Resources\Api\ProjectDetailResource($proyecto->fresh()))->toArray(request());

        $this->assertEquals('#112233', $resource['map']['bg_color']);
        $this->assertEquals('#112233', $resource['entorno']['bg_color']);
        $this->assertEquals('above', $resource['interiorismo']['name_position']);
    }

    public function test_edit_proyecto_persists_carousel_spacing_with_px_default_and_zero_fallback(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.carousel_spacing.project.top', '20')
            ->set('data.detail.carousel_spacing.project.bottom', '2rem')
            ->set('data.detail.carousel_spacing.equipment.top', '')
            ->call('save')
            ->assertHasNoFormErrors();

        $resource = (new \App\Http\Resources\Api\ProjectDetailResource($proyecto->fresh()))->toArray(request());

        $this->assertSame(['top' => '20px', 'bottom' => '2rem'], $resource['project_section']['carousel_spacing']);
        $this->assertSame(['top' => '0', 'bottom' => '0'], $resource['equipment']['carousel_spacing']);
        $this->assertSame(['top' => '0', 'bottom' => '0'], $resource['espacios']['carousel_spacing']);
    }

    public function test_edit_proyecto_rejects_invalid_carousel_spacing(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.carousel_spacing.project.top', 'url(x)')
            ->call('save')
            ->assertHasFormErrors(['detail.carousel_spacing.project.top']);
    }

    public function test_edit_proyecto_persists_map_pin_image_and_name_and_injects_project_poi(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $this->seed(InitialInnProjectDetailSeeder::class);

        $proyecto = Proyecto::where('slug', 'edificio-inn')->firstOrFail();

        Livewire::test(EditProyecto::class, ['record' => $proyecto->id])
            ->set('data.detail.map_pin_image_url', 'images/inn/custom_fachada.jpg')
            ->set('data.detail.map_pin_name', 'Edificio INN Principal')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('project_details', [
            'project_id' => $proyecto->id,
            'map_pin_image_url' => 'images/inn/custom_fachada.jpg',
            'map_pin_name' => 'Edificio INN Principal',
        ]);

        $resource = (new \App\Http\Resources\Api\ProjectDetailResource($proyecto->fresh()))->toArray(request());

        $this->assertEquals('images/inn/custom_fachada.jpg', $resource['map']['pin']['image_url']);
        $this->assertEquals('Edificio INN Principal', $resource['map']['pin']['name']);
        $this->assertNotEmpty($resource['map']['pois']);
        $this->assertTrue($resource['map']['pois'][0]['is_project']);
        $this->assertEquals('Edificio INN Principal', $resource['map']['pois'][0]['name']);
        $this->assertEquals('images/inn/custom_fachada.jpg', $resource['map']['pois'][0]['img']);
    }
}
