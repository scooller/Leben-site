<?php

namespace Database\Seeders;

use App\Models\CuratorMedia;
use App\Models\Proyecto;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class TestimonialSeeder extends Seeder
{
    /**
     * Seed testimonials with Curator media avatars
     */
    public function run(): void
    {
        $innProject = Proyecto::query()->where('slug', 'inn')->orWhere('slug', 'edificio-inn')->first();

        $avatars = [
            'herman' => [
                'filename' => 'herman.jpg',
                'title' => 'Avatar Herman Oettinger',
            ],
            'fernando' => [
                'filename' => 'fernando.jpg',
                'title' => 'Avatar Fernando Cid',
            ],
            'john' => [
                'filename' => 'john.jpg',
                'title' => 'Avatar John Von Achon',
            ],
        ];

        $mediaMap = [];
        $disk = Storage::disk('curator');

        foreach ($avatars as $key => $meta) {
            $relativePath = 'testimonials/' . $meta['filename'];
            $existing = CuratorMedia::where('path', $relativePath)->first();

            if ($existing) {
                $mediaMap[$key] = $existing;
                continue;
            }

            if ($disk->exists($relativePath)) {
                $fullPath = $disk->path($relativePath);
                $size = @filesize($fullPath) ?: 1000;
                $imageSize = @getimagesize($fullPath) ?: [400, 400];
                $mime = $imageSize['mime'] ?? 'image/jpeg';
                $ext = pathinfo($meta['filename'], PATHINFO_EXTENSION) ?: 'jpg';

                $mediaMap[$key] = CuratorMedia::create([
                    'disk' => 'curator',
                    'directory' => 'testimonials',
                    'visibility' => 'public',
                    'name' => pathinfo($meta['filename'], PATHINFO_FILENAME),
                    'path' => $relativePath,
                    'width' => $imageSize[0] ?? 400,
                    'height' => $imageSize[1] ?? 400,
                    'size' => $size,
                    'type' => $mime,
                    'ext' => $ext,
                    'title' => $meta['title'],
                    'pretty_name' => $meta['title'],
                ]);
            }
        }

        $testimonials = [
            [
                'name' => 'Herman Oettinger',
                'role' => 'Propietario en Puerto Varas',
                'avatar_id' => $mediaMap['herman']?->id ?? null,
                'avatar_url' => $mediaMap['herman']?->url ?? '/images/home/avatar-herman.jpg',
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
                'avatar_id' => $mediaMap['fernando']?->id ?? null,
                'avatar_url' => $mediaMap['fernando']?->url ?? '/images/home/avatar-fernanda.jpg',
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
                'avatar_id' => $mediaMap['john']?->id ?? null,
                'avatar_url' => $mediaMap['john']?->url ?? '/images/home/avatar-maximiliano.jpg',
                'rating' => 5.0,
                'review_text' => 'La calidad arquitectónica, los espacios comunes como la bodega náutica y el diseño interior de Sofía Iturralde marcan un estándar único en el sur de Chile.',
                'review_date_human' => 'hace 1 año',
                'is_published' => true,
                'sort_order' => 3,
                'project_id' => $innProject?->id,
            ],
        ];

        foreach ($testimonials as $data) {
            Testimonial::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }

        $this->command?->info(count($testimonials) . ' testimonios sembrados exitosamente.');
    }
}
