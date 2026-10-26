<?php

namespace Database\Seeders;

use App\Models\ProjectAmenity;
use App\Models\ProjectDetail;
use App\Models\ProjectGallery;
use App\Models\ProjectPoi;
use App\Models\Proyecto;
use Illuminate\Database\Seeder;

class InitialInnProjectDetailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Proyecto::where('slug', 'edificio-inn')
            ->orWhere('slug', 'inn')
            ->first();

        if (! $project) {
            $project = Proyecto::firstOrCreate(
                ['slug' => 'edificio-inn'],
                [
                    'salesforce_id' => 'a01000000000001AAA',
                    'name' => 'Edificio INN',
                    'comuna' => 'Puerto Varas',
                    'region' => 'Los Lagos',
                    'is_active' => true,
                    'sort_order' => 1,
                ]
            );
        }

        $detail = ProjectDetail::updateOrCreate(
            ['project_id' => $project->id],
            [
                'hero_eyebrow' => '1. Proyecto | Único en su clase',
                'hero_title' => 'El Sur se vive frente al lago',
                'hero_subtitle' => 'Conexión Urbana & Paisajismo Interior',
                'hero_video_url' => '/storage/video/hero-inn.mp4',
                'hero_logo_url' => '/storage/inn/inn-logo.png',
                'theme_colors' => [
                    'accent' => '#d9bc70',
                    'accent_dark' => '#c4a355',
                    'accent_light' => '#e2ca8d',
                    'accent_bg' => 'rgba(217, 188, 112, 0.12)',
                    'gradient' => 'linear-gradient(135deg, #d9bc70 0%, #b89345 100%)',
                    'btn_shadow' => '0 8px 24px rgba(217, 188, 112, 0.35)',
                ],
                'schedule' => [
                    'Lun a Vie: 10:00 a 14:00 hrs y 15:00 a 19:00 hrs',
                    'Sáb y Dom: 11:00 a 18:00 hrs continuado',
                ],
                'announcement' => [
                    'enabled' => false,
                    'id' => 'inn-atencion-especial',
                    'title' => 'Sala de Ventas Puerto Varas',
                    'message' => 'Visítanos en Vicente Pérez Rosales 991 con estacionamiento para clientes.',
                    'button_text' => 'Ver ubicación',
                ],
                'technical_info' => [
                    ['id' => 'direccion', 'label' => 'Dirección', 'value' => 'Vicente Pérez Rosales 991,<br /> Puerto Varas'],
                    ['id' => 'tipologias', 'label' => 'Tipologías', 'value' => 'Deptos de 2, 3 y 4 dorms, dúplex <br />y deptos con patio privado'],
                    ['id' => 'metrajes', 'label' => 'Metrajes', 'value' => 'Desde 85 m²'],
                    ['id' => 'precio', 'label' => 'Precio desde', 'value' => 'UF 9.816'],
                    ['id' => 'estado', 'label' => 'Estado del proyecto', 'value' => 'Venta en Verde'],
                ],
                'project_slides' => [
                    ['img' => '/storage/inn/proyecto/Proyecto_01_Acceso.jpg', 'alt' => 'Acceso'],
                    ['img' => '/storage/inn/proyecto/Proyecto_02_Cocina.jpg', 'alt' => 'Cocina'],
                ],
                'equipment_brands' => [
                    ['src' => '/storage/logos/franke.png', 'alt' => 'Franke'],
                    ['src' => '/storage/logos/mk.png', 'alt' => 'MK'],
                ],
                'equipment_slides' => [
                    ['img' => '/storage/inn/equipamiento/Equipamiento_Principal_Hall_Acceso.jpg', 'alt' => 'Hall de acceso'],
                ],
                'overview_title' => 'El Sur se vive frente al lago',
                'overview_description' => 'Descubre el privilegio de vivir en primera línea frente al Lago Llanquihue con vistas panorámicas a los volcanes Osorno y Calbuco.',
                'overview_images' => [
                    'left' => '/storage/inn/inn-overview-1.jpg',
                    'right_top' => '/storage/inn/inn-overview-2.jpg',
                    'right_bottom' => '/storage/inn/inn-overview-3.jpg',
                ],
                'overview_stats' => [
                    ['value' => '2 - 3 - 4', 'label' => 'Dormitorios'],
                    ['value' => 'Desde 85 m²', 'label' => 'Superficie útil'],
                    ['value' => 'Franke', 'label' => 'Equipamiento en cocina'],
                ],
                'terminaciones_title' => 'Terminaciones',
                'terminaciones_description' => 'Equipamiento premium marca Franke: horno empotrado, campana retráctil, encimera vitrocerámica y cubiertas de cuarzo.',
                'terminaciones_items' => [
                    ['label' => 'Horno Franke', 'icon' => 'flame', 'image_url' => '/storage/inn/terminaciones/horno.jpg'],
                    ['label' => 'Encimera vitrocerámica', 'icon' => 'sparkles', 'image_url' => '/storage/inn/terminaciones/encimera.jpg'],
                    ['label' => 'Cubierta de cuarzo', 'icon' => 'gem', 'image_url' => '/storage/inn/terminaciones/cuarzo.jpg'],
                ],
                'virtual_tour_matterport_url' => 'https://my.matterport.com/show/?m=hQ8Fm33FqFY&brand=0',
                'virtual_tour_360_url' => 'https://www.lanube360.com/ileben1/',
                'virtual_tour_video_url' => null,
                'brochure_pdf_url' => '/storage/inn/brochure-inn.pdf',
                'designers' => [
                    [
                        'name' => 'Sofía Iturralde<br/><small>Interiorista</small>',
                        'image' => '/storage/inn/Foto_Iturralde.jpg',
                        'alt' => 'Diseño de Sofía Iturralde',
                        'color' => '#d9bc70',
                    ],
                    [
                        'name' => 'Rafael Rivera<br/><small>Iluminador</small>',
                        'image' => '/storage/inn/Foto_Rivera.jpg',
                        'alt' => 'Diseño de Rafael Rivera',
                        'color' => '#b78d56',
                    ],
                ],
                'panoramas' => [
                    [
                        'category' => 'Naturaleza',
                        'title' => 'Lago Llanquihue y Volcanes',
                        'image' => '/storage/inn/panoramas/lago.jpg',
                        'description' => 'Vistas despejadas y navegación náutica de primer nivel.',
                    ],
                    [
                        'category' => 'Gastronomía',
                        'title' => 'Costanera Gastronómica',
                        'image' => '/storage/inn/panoramas/gastronomia.jpg',
                        'description' => 'Restaurantes emblemáticos y cervecerías artesanales a pasos.',
                    ],
                ],
                'entorno_description' => 'Ubicado en Vicente Pérez Rosales, Edificio INN te sitúa a pasos del Lago Llanquihue, centros comerciales, colegios y el centro de Puerto Varas.',
                'entorno_map_image_url' => '/storage/inn/mapa-entorno.jpg',
            ]
        );

        // Project Amenities
        ProjectAmenity::where('project_detail_id', $detail->id)->delete();
        $amenities = [
            ['name' => 'Hall de acceso', 'icon_key' => 'bell', 'image_url' => '/storage/inn/eecc/EECC_Hall_Acceso_Edificio.jpg', 'sort_order' => 1],
            ['name' => 'Bodega náutica', 'icon_key' => 'kayak', 'image_url' => '/storage/inn/eecc/EECC_Bodega_Nautica.jpg', 'sort_order' => 2],
            ['name' => 'Piscina & Hot Tub', 'icon_key' => 'hot-tub', 'image_url' => '/storage/inn/eecc/EECC_Piscina.jpg', 'sort_order' => 3],
            ['name' => 'Gimnasio Equipado', 'icon_key' => 'dumbbell', 'image_url' => '/storage/inn/eecc/EECC_Gym.jpg', 'sort_order' => 4],
            ['name' => 'Quincho Gourmet', 'icon_key' => 'chef-hat', 'image_url' => '/storage/inn/eecc/EECC_Quincho.jpg', 'sort_order' => 5],
        ];
        foreach ($amenities as $item) {
            ProjectAmenity::create(array_merge($item, ['project_detail_id' => $detail->id]));
        }

        // Project POIs
        ProjectPoi::where('project_detail_id', $detail->id)->delete();
        $pois = [
            [
                'name' => 'Mesa Tropera',
                'category' => 'Gastronomía',
                'distance' => '1.2 km',
                'walking_time' => '12 minutos',
                'coordinate_x' => 15.1,
                'coordinate_y' => 33.2,
                'pin_direction' => 'right',
                'image_url' => '/storage/inn/ubicacion/Mesa_Tropera_AI.jpg',
                'sort_order' => 1,
            ],
            [
                'name' => 'Supermercado Jumbo',
                'category' => 'Supermercado',
                'distance' => '900 m',
                'walking_time' => '9 minutos',
                'coordinate_x' => 42.5,
                'coordinate_y' => 58.0,
                'pin_direction' => 'left',
                'image_url' => '/storage/inn/ubicacion/jumbo.jpg',
                'sort_order' => 2,
            ],
            [
                'name' => 'Muelle Histórico',
                'category' => 'Interés',
                'distance' => '1.5 km',
                'walking_time' => '15 minutos',
                'coordinate_x' => 75.3,
                'coordinate_y' => 22.4,
                'pin_direction' => 'bottom',
                'image_url' => '/storage/inn/ubicacion/muelle.jpg',
                'sort_order' => 3,
            ],
        ];
        foreach ($pois as $item) {
            ProjectPoi::create(array_merge($item, ['project_detail_id' => $detail->id]));
        }

        // Project Galleries
        ProjectGallery::where('project_detail_id', $detail->id)->delete();
        $galleries = [
            ['category' => 'bottom', 'title' => 'Vista fachada lago', 'image_url' => '/storage/inn/galeria1.jpg', 'alt_text' => 'Fachada lago', 'sort_order' => 1],
            ['category' => 'bottom', 'title' => 'Living y terraza', 'image_url' => '/storage/inn/galeria2.jpg', 'alt_text' => 'Living amplio', 'sort_order' => 2],
            ['category' => 'spaces', 'title' => 'Cocina integrada', 'image_url' => '/storage/inn/galeria3.jpg', 'alt_text' => 'Cocina equipada', 'sort_order' => 1],
        ];
        foreach ($galleries as $item) {
            ProjectGallery::create(array_merge($item, ['project_detail_id' => $detail->id]));
        }
    }
}
