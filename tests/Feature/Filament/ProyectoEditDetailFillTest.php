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
}
