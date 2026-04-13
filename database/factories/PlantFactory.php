<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plant>
 */
class PlantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salesforce_product_id' => fake()->unique()->uuid(),
            'salesforce_proyecto_id' => fake()->uuid(),
            'name' => fake()->numberBetween(101, 999),
            'product_code' => 'PLANT-'.fake()->unique()->numberBetween(1000, 9999),
            'tipo_producto' => fake()->randomElement(['DEPARTAMENTO', 'ESTACIONAMIENTO', 'BODEGA', 'LOCAL']),
            'orientacion' => fake()->randomElement(['Norte', 'Sur', 'Este', 'Oeste', 'Nor-Este', 'Nor-Oeste']),
            'programa' => fake()->numberBetween(1, 4).' dormitorios',
            'programa2' => fake()->numberBetween(1, 3).' baños',
            'piso' => fake()->numberBetween(1, 20),
            'precio_base' => fake()->randomFloat(2, 3000, 10000),
            'precio_lista' => fake()->randomFloat(2, 3200, 10500),
            'porcentaje_maximo_unidad' => fake()->randomFloat(2, 0, 100),
            'unidad_sale' => fake()->boolean(20),
            'superficie_total_principal' => fake()->randomFloat(2, 40, 150),
            'superficie_interior' => fake()->randomFloat(2, 35, 120),
            'superficie_util' => fake()->randomFloat(2, 30, 100),
            'superficie_terraza' => fake()->randomFloat(2, 5, 30),
            'is_active' => fake()->boolean(80),
            'last_synced_at' => now(),
        ];
    }
}
