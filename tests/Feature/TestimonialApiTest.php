<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_testimonials_index_returns_published_testimonials(): void
    {
        Testimonial::create([
            'name' => 'Cliente Feliz',
            'role' => 'Propietario en Puerto Varas',
            'review_text' => 'Excelente experiencia de compra.',
            'rating' => 5.0,
            'is_published' => true,
            'sort_order' => 1,
        ]);

        Testimonial::create([
            'name' => 'Cliente Oculto',
            'role' => 'Inversionista',
            'review_text' => 'No debe aparecer.',
            'rating' => 4.0,
            'is_published' => false,
            'sort_order' => 2,
        ]);

        $response = $this->getJson('/api/v1/testimonials');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cliente Feliz')
            ->assertJsonPath('data.0.role', 'Propietario en Puerto Varas')
            ->assertJsonPath('data.0.rating', 5);
    }
}
