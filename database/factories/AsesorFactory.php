<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asesor>
 */
class AsesorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salesforce_id' => fake()->optional()->regexify('005[A-Za-z0-9]{12}'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'whatsapp_owner' => fake()->phoneNumber(),
            'avatar_url' => fake()->imageUrl(240, 240, 'people'),
            'is_active' => fake()->boolean(90),
        ];
    }
}
