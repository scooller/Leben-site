<?php

namespace Database\Factories;

use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Proyecto>
 */
class ProyectoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salesforce_id' => fake()->unique()->uuid(),
            'name' => fake()->company().' '.fake()->randomElement(['Tower', 'Residencial', 'Edificio']),
            'descripcion' => fake()->sentence(10),
            'direccion' => fake()->streetAddress(),
            'comuna' => fake()->randomElement(['Santiago', 'Providencia', 'Las Condes', 'Vitacura', 'Ñuñoa']),
            'provincia' => 'Santiago',
            'region' => 'Metropolitana',
            'email' => fake()->companyEmail(),
            'telefono' => fake()->phoneNumber(),
            'pagina_web' => fake()->url(),
            'razon_social' => fake()->company(),
            'rut' => fake()->numerify('########-#'),
            'fecha_inicio_ventas' => fake()->dateTimeBetween('-1 year', 'now'),
            'fecha_entrega' => fake()->dateTimeBetween('now', '+2 years'),
            'etapa' => fake()->randomElement(array_keys(Proyecto::etapaOptions())),
            'horario_atencion' => 'Lunes a Viernes 9:00 - 18:00',
            'valor_reserva_exigido_defecto_peso' => fake()->randomFloat(2, 100000, 500000),
            'valor_reserva_exigido_min_peso' => fake()->randomFloat(2, 50000, 200000),
            'descuento_defecto_cotizacion_web' => fake()->optional(0.5)->randomFloat(2, 0, 20),
            'entrega_inmediata' => fake()->boolean(30),
        ];
    }
}
