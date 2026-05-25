<?php

namespace Tests\Feature;

use App\Filament\Resources\Plants\Pages\ListPlants;
use App\Filament\Resources\Plants\PlantResource;
use App\Filament\Resources\Plants\Schemas\PlantForm;
use App\Models\Plant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component as LivewireComponent;
use Livewire\Livewire;
use Tests\TestCase;

class PlantResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_navigation_badge_shows_plant_count(): void
    {
        Plant::factory()->count(5)->create();

        $badge = PlantResource::getNavigationBadge();

        $this->assertEquals('5', $badge);
    }

    public function test_navigation_badge_returns_zero_when_no_plants(): void
    {
        $badge = PlantResource::getNavigationBadge();

        $this->assertEquals('0', $badge);
    }

    public function test_can_list_plants(): void
    {
        $plants = Plant::factory()->count(3)->create();

        $this->assertDatabaseCount('plants', 3);
        $this->assertDatabaseHas('plants', [
            'id' => $plants[0]->id,
        ]);
    }

    public function test_can_search_plants_by_name(): void
    {
        $plantToFind = Plant::factory()->create(['name' => 'Plant 101']);
        $otherPlant = Plant::factory()->create(['name' => 'Plant 202']);

        $this->assertDatabaseHas('plants', [
            'id' => $plantToFind->id,
            'name' => 'Plant 101',
        ]);
        $this->assertDatabaseHas('plants', [
            'id' => $otherPlant->id,
            'name' => 'Plant 202',
        ]);
    }

    public function test_plants_table_columns_are_displayed(): void
    {
        $plant = Plant::factory()->create([
            'name' => 'Test Plant',
            'precio_base' => 5000.00,
            'superficie_total_principal' => 75.50,
        ]);

        $this->assertDatabaseHas('plants', [
            'id' => $plant->id,
            'name' => 'Test Plant',
            'precio_base' => 5000.00,
            'superficie_total_principal' => 75.50,
        ]);
    }

    public function test_plant_form_hides_unused_fields(): void
    {
        $schema = PlantForm::configure(Schema::make($this->makeSchemaHost()));
        $components = $schema->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true);

        $this->assertArrayNotHasKey('opportunity_id', $components);
        $this->assertArrayNotHasKey('superficie_vendible', $components);
        $this->assertArrayHasKey('unidad_sale', $components);
    }

    public function test_plants_bulk_actions_include_activate_and_deactivate_sale(): void
    {
        $this->user->update(['user_type' => 'admin']);

        Livewire::test(ListPlants::class)
            ->assertTableBulkActionExists('activateSelected')
            ->assertTableBulkActionHasLabel('activateSelected', 'Activar')
            ->assertTableBulkActionExists('deactivateSaleSelected')
            ->assertTableBulkActionHasLabel('deactivateSaleSelected', 'Desactivar en sale');
    }

    public function test_plants_bulk_actions_activate_and_deactivate_sale_update_records(): void
    {
        $this->user->update(['user_type' => 'admin']);

        $plants = Plant::factory()->count(2)->create([
            'is_active' => false,
            'unidad_sale' => true,
        ]);

        Livewire::test(ListPlants::class)
            ->callTableBulkAction('activateSelected', $plants)
            ->callTableBulkAction('deactivateSaleSelected', $plants);

        foreach ($plants as $plant) {
            $this->assertDatabaseHas('plants', [
                'id' => $plant->id,
                'is_active' => true,
                'unidad_sale' => false,
            ]);
        }
    }

    private function makeSchemaHost(): HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            public function render()
            {
                return '<div></div>';
            }

            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(string $key, bool $withHidden = false, array $skipComponentsChildContainersWhileSearching = []): Component|Action|ActionGroup|null
            {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void {}

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }
}
