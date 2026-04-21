<?php

namespace Database\Factories;

use App\Models\ContactChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactChannel>
 */
class ContactChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'is_active' => true,
            'is_default' => false,
            'notification_email' => $this->faker->safeEmail(),
            'form_fields' => null,
            'domain_patterns' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => 'default',
            'name' => 'Default',
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
