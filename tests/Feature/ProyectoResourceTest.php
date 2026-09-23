<?php

namespace Tests\Feature;

use App\Filament\Resources\ProyectoResource;
use App\Filament\Resources\Proyectos\Schemas\ProyectoForm;
use App\Models\Proyecto;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component as LivewireComponent;
use Tests\TestCase;

class ProyectoResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_navigation_badge_shows_proyecto_count(): void
    {
        Proyecto::factory()->count(3)->create();

        $badge = ProyectoResource::getNavigationBadge();

        $this->assertEquals('3', $badge);
    }

    public function test_navigation_badge_returns_zero_when_no_proyectos(): void
    {
        $badge = ProyectoResource::getNavigationBadge();

        $this->assertEquals('0', $badge);
    }

    public function test_can_list_proyectos(): void
    {
        $proyectos = Proyecto::factory()->count(3)->create();

        $this->assertDatabaseCount('proyectos', 3);
        $this->assertDatabaseHas('proyectos', [
            'id' => $proyectos[0]->id,
        ]);
    }

    public function test_can_search_proyectos_by_name(): void
    {
        $proyectoToFind = Proyecto::factory()->create(['name' => 'Torre Especial']);
        $otherProyecto = Proyecto::factory()->create(['name' => 'Edificio Común']);

        $this->assertDatabaseHas('proyectos', [
            'id' => $proyectoToFind->id,
            'name' => 'Torre Especial',
        ]);
        $this->assertDatabaseHas('proyectos', [
            'id' => $otherProyecto->id,
            'name' => 'Edificio Común',
        ]);
    }

    public function test_can_create_proyecto(): void
    {
        $proyectoData = [
            'salesforce_id' => fake()->unique()->uuid(),
            'name' => 'Test Proyecto',
            'descripcion' => 'Descripción test',
            'direccion' => 'Calle test 123',
            'comuna' => 'Santiago',
            'provincia' => 'Santiago',
            'region' => 'Metropolitana',
            'email' => 'test@example.com',
            'telefono' => '123456789',
            'pagina_web' => 'https://test.com',
            'razon_social' => 'Test SpA',
            'rut' => '12345678-9',
        ];

        $proyecto = Proyecto::create($proyectoData);

        $this->assertDatabaseHas('proyectos', [
            'id' => $proyecto->id,
            'name' => 'Test Proyecto',
        ]);
    }

    public function test_proyecto_name_is_required(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Proyecto::create([
            'name' => null,
        ]);
    }

    public function test_proyecto_form_hides_unused_financing_and_discount_fields(): void
    {
        $schema = ProyectoForm::configure(Schema::make($this->makeSchemaHost()));
        $components = $schema->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true);

        foreach ([
            'dscto_m_x_prod_principal_porc',
            'dscto_m_x_prod_principal_uf',
            'dscto_m_x_bodega_porc',
            'dscto_m_x_bodega_uf',
            'dscto_m_x_estac_porc',
            'dscto_m_x_estac_uf',
            'dscto_max_otros_porc',
            'dscto_max_otros_prod_uf',
            'dscto_maximo_aporte_leben',
            'n_anos_1',
            'n_anos_2',
            'n_anos_3',
            'n_anos_4',
            'tasa',
        ] as $field) {
            $this->assertArrayNotHasKey($field, $components);
        }
    }

    public function test_proyecto_form_configures_discount_fields_correctly(): void
    {
        $schema = ProyectoForm::configure(Schema::make($this->makeSchemaHost()));
        $components = $schema->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true);

        $this->assertArrayHasKey('descuento_iva', $components);
        $this->assertArrayHasKey('descuento_defecto_cotizacion_web', $components);
        $this->assertArrayHasKey('descuento_maximo_unidad', $components);

        $ivaComponent = $components['descuento_iva'];
        $this->assertSame('Dcto. IVA', $ivaComponent->getLabel());
        $this->assertSame('%', $ivaComponent->getSuffixLabel());
        $this->assertFalse($ivaComponent->isDisabled());
        $this->assertSame(0, $ivaComponent->getDefaultState());

        $defectoComponent = $components['descuento_defecto_cotizacion_web'];
        $this->assertFalse($defectoComponent->isDisabled());

        $maximoComponent = $components['descuento_maximo_unidad'];
        $this->assertFalse($maximoComponent->isDisabled());
        $this->assertTrue($maximoComponent->isDehydrated());
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
