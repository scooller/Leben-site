<?php

namespace Database\Seeders;

use App\Models\Proyecto;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class InitialTestimonialsSeeder extends Seeder
{
    public function run(): void
    {
        $innProject = Proyecto::query()->where('slug', 'inn')->first();

        $testimonials = [
            [
                'name' => 'Herman Oettinger',
                'role' => 'Propietario en Puerto Varas',
                'avatar_url' => 'https://dev.ileben.cl/storage/testimonials/herman.jpg',
                'rating' => 5.0,
                'review_text' => 'Comprar en Edificio INN fue una experiencia insuperable. Las terminaciones Franke y las vistas panorámicas al lago Llanquihue superaron todas nuestras expectativas familiares.',
                'review_date_human' => 'hace 6 meses',
                'is_published' => true,
                'sort_order' => 1,
                'project_id' => $innProject?->id,
            ],
            [
                'name' => 'Fernando Cid',
                'role' => 'Inversionista Leben Invest',
                'avatar_url' => 'https://dev.ileben.cl/storage/testimonials/fernando.jpg',
                'rating' => 4.9,
                'review_text' => 'El acompañamiento del equipo comercial y la rentabilidad obtenida mediante el plan sin pie de Leben Invest han sido extraordinarios. Transparencia y seriedad total.',
                'review_date_human' => 'hace 11 meses',
                'is_published' => true,
                'sort_order' => 2,
                'project_id' => $innProject?->id,
            ],
            [
                'name' => 'John Von Achon',
                'role' => 'Propietario en Puerto Varas',
                'avatar_url' => 'https://dev.ileben.cl/storage/testimonials/john.jpg',
                'rating' => 5.0,
                'review_text' => 'La calidad arquitectónica, los espacios comunes como la bodega náutica y el diseño interior de Sofía Iturralde marcan un estándar único en el sur de Chile.',
                'review_date_human' => 'hace 1 año',
                'is_published' => true,
                'sort_order' => 3,
                'project_id' => $innProject?->id,
            ],
        ];

        foreach ($testimonials as $data) {
            Testimonial::query()->firstOrCreate(
                ['name' => $data['name'], 'role' => $data['role']],
                $data
            );
        }
    }
}
