<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProyectoApiPriceAndTypologiesTest extends TestCase
{
    use RefreshDatabase;

    private function validAuthHeaders(): array
    {
        $user = User::factory()->create();

        return ['Authorization' => 'Bearer ' . $user->createToken('test', ['*'])->plainTextToken];
    }

    public function test_listado_includes_precio_desde_and_tipologias_by_default(): void
    {
        $proyecto = Proyecto::factory()->create(['is_active' => true]);

        Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'programa' => '2 dormitorios',
            'programa2' => '1 baño',
            'tipo_producto' => 'DEPARTAMENTO',
            'precio_lista' => 3000,
            'superficie_util' => 50,
            'is_active' => true,
        ]);

        Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'programa' => '2 dormitorios',
            'programa2' => '1 baño',
            'tipo_producto' => 'DEPARTAMENTO',
            'precio_lista' => 2800,
            'superficie_util' => 48,
            'is_active' => true,
        ]);

        Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'programa' => '3 dormitorios',
            'programa2' => '2 baños',
            'tipo_producto' => 'DEPARTAMENTO',
            'precio_lista' => 5000,
            'superficie_util' => 70,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/proyectos', $this->validAuthHeaders());

        $response->assertOk();

        $data = $response->json('data.0');

        $this->assertEquals(2800.0, $data['precio_desde']);
        $this->assertCount(2, $data['tipologias']);

        $tip2D = collect($data['tipologias'])->firstWhere('programa', '2 dormitorios');
        $this->assertNotNull($tip2D);
        $this->assertSame('1 baño', $tip2D['programa2']);
        $this->assertSame('DEPARTAMENTO', $tip2D['tipo_producto']);
        $this->assertSame(2, $tip2D['cantidad']);
        $this->assertEquals(2800.0, $tip2D['precio_desde']);
        $this->assertEquals(48.0, $tip2D['superficie_util_min']);
        $this->assertEquals(50.0, $tip2D['superficie_util_max']);

        $tip3D = collect($data['tipologias'])->firstWhere('programa', '3 dormitorios');
        $this->assertNotNull($tip3D);
        $this->assertSame(1, $tip3D['cantidad']);
        $this->assertEquals(5000.0, $tip3D['precio_desde']);
        $this->assertEquals(70.0, $tip3D['superficie_util_min']);
        $this->assertEquals(70.0, $tip3D['superficie_util_max']);
    }

    public function test_detalle_includes_precio_desde_and_tipologias(): void
    {
        $proyecto = Proyecto::factory()->create(['is_active' => true]);

        Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'programa' => '1 dormitorio',
            'programa2' => '1 baño',
            'tipo_producto' => 'DEPARTAMENTO',
            'precio_lista' => 2000,
            'superficie_util' => 40,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/proyectos/{$proyecto->id}", $this->validAuthHeaders());

        $response->assertOk();
        $this->assertEquals(2000, $response->json('precio_desde'));
        $response->assertJsonCount(1, 'tipologias')
            ->assertJsonPath('tipologias.0.programa', '1 dormitorio')
            ->assertJsonPath('tipologias.0.programa2', '1 baño')
            ->assertJsonPath('tipologias.0.tipo_producto', 'DEPARTAMENTO')
            ->assertJsonPath('tipologias.0.cantidad', 1);
        $this->assertEquals(40, $response->json('tipologias.0.superficie_util_min'));
        $this->assertEquals(40, $response->json('tipologias.0.superficie_util_max'));
    }

    public function test_proyecto_sin_plantas_devuelve_null_y_array_vacio(): void
    {
        $proyecto = Proyecto::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/proyectos/{$proyecto->id}", $this->validAuthHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('precio_desde', null)
            ->assertJsonCount(0, 'tipologias');
    }

    public function test_tipologias_excludes_inactive_plants(): void
    {
        $proyecto = Proyecto::factory()->create(['is_active' => true]);

        Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'programa' => '2 dormitorios',
            'programa2' => '1 baño',
            'tipo_producto' => 'DEPARTAMENTO',
            'precio_lista' => 3000,
            'superficie_util' => 50,
            'is_active' => true,
        ]);

        Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'programa' => '4 dormitorios',
            'programa2' => '3 baños',
            'tipo_producto' => 'DEPARTAMENTO',
            'precio_lista' => 100,
            'superficie_util' => 200,
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/v1/proyectos/{$proyecto->id}", $this->validAuthHeaders());

        $response->assertOk();

        $this->assertEquals(3000.0, $response->json('precio_desde'));
        $this->assertCount(1, $response->json('tipologias'));
        $this->assertSame('2 dormitorios', $response->json('tipologias.0.programa'));
    }

    public function test_fields_selection_excludes_precio_desde_when_not_requested(): void
    {
        $proyecto = Proyecto::factory()->create(['is_active' => true]);

        Plant::factory()->create([
            'salesforce_proyecto_id' => $proyecto->salesforce_id,
            'precio_lista' => 3000,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/proyectos/{$proyecto->id}?fields=id,name", $this->validAuthHeaders());

        $response->assertOk();
        $this->assertArrayNotHasKey('precio_desde', $response->json());
        $this->assertArrayNotHasKey('tipologias', $response->json());
    }
}
