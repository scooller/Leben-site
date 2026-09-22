<?php

namespace Tests\Feature\Filament\Actions;

use App\Filament\Actions\ResetSalePlantsAction;
use App\Filament\Resources\Plants\Pages\ListPlants;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResetSalePlantsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_sale_plants_successfully_unmarks_all_sale_plants(): void
    {
        $project = Proyecto::factory()->create();

        Plant::factory()->count(3)->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'unidad_sale' => true,
        ]);

        Plant::factory()->count(2)->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'unidad_sale' => false,
        ]);

        self::assertSame(3, Plant::query()->where('unidad_sale', true)->count());

        $result = ResetSalePlantsAction::execute();

        self::assertTrue($result['success']);
        self::assertSame(3, $result['count']);
        self::assertStringContainsString('3', $result['message']);
        self::assertSame(0, Plant::query()->where('unidad_sale', true)->count());
        self::assertSame(5, Plant::query()->count());
    }

    public function test_reset_sale_plants_when_no_sale_plants_exist(): void
    {
        $project = Proyecto::factory()->create();

        Plant::factory()->count(2)->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'unidad_sale' => false,
        ]);

        $result = ResetSalePlantsAction::execute();

        self::assertTrue($result['success']);
        self::assertSame(0, $result['count']);
        self::assertSame('No había plantas asignadas como unidad Sale', $result['message']);
        self::assertSame(0, Plant::query()->where('unidad_sale', true)->count());
    }

    public function test_list_plants_header_action_is_registered_and_executable(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $project = Proyecto::factory()->create();

        Plant::factory()->count(2)->create([
            'salesforce_proyecto_id' => $project->salesforce_id,
            'unidad_sale' => true,
        ]);

        self::assertSame(2, Plant::query()->where('unidad_sale', true)->count());

        Livewire::actingAs($admin)
            ->test(ListPlants::class)
            ->assertActionExists('reset_sale_plants')
            ->callAction('reset_sale_plants')
            ->assertHasNoActionErrors();

        self::assertSame(0, Plant::query()->where('unidad_sale', true)->count());
    }
}
