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
                    'direccion' => 'Vicente Pérez Rosales 991, Puerto Varas',
                    'is_active' => true,
                    'sort_order' => 1,
                ]
            );
        }

        $detail = ProjectDetail::updateOrCreate(
            ['project_id' => $project->id],
            [
                'hero_eyebrow' => 'Puerto Varas',
                'hero_title' => "VIVE EL LUJO\nEN PUERTO VARAS",
                'hero_subtitle' => 'Departamentos en primera línea frente al lago Llanquihue',
                'hero_video_url' => 'video/inn-new.mp4',
                'hero_logo_url' => 'images/inn/Logo_INN_Header.png',
                'theme_colors' => [
                    'accent' => '#d9bc70',
                    'accent_dark' => '#422c15',
                    'accent_light' => '#f9f2e7',
                    'accent_bg' => '#faf4e8',
                    'gradient' => 'linear-gradient(180deg, rgba(158, 122, 76, 1) 0%, rgba(216, 178, 92, 1) 65%, rgba(216, 178, 92, 1) 100%)',
                    'btn_shadow' => 'rgba(158, 122, 76, 0.25)',
                ],
                'schedule' => [
                    'Lun a Dom: 10:00 a 14:00 y 15:00 a 19:00 hrs',
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
                    ['id' => 'estado', 'label' => 'Estado del proyecto', 'value' => 'Entrega futura'],
                ],

                // Sección 1: Proyecto
                'project_eyebrow' => 'Proyecto',
                'project_title' => "LOS MEJORES DEPARTAMENTOS\nDE PUERTO VARAS",
                'project_description' => '<p>INN propone una nueva forma de vivir Puerto Varas, con espacios amplios y luminosos, terminaciones de alto estándar y distribuciones pensadas para disfrutar todo el año.</p><p>Departamentos tradicionales, unidades con patio privado y exclusivos dúplex con vista al lago Llanquihue se complementan con una experiencia Home & Wellness que invita a relajarse, desconectarse y disfrutar el sur con la esencia de un hotel boutique.</p>',
                'project_highlight_offer' => 'paga el pie en <b>60</b> cuotas',
                'project_slides' => [
                    ['img' => 'images/inn/proyecto/Proyecto_01_Acceso.jpg', 'alt' => 'Acceso'],
                    ['img' => 'images/inn/proyecto/Proyecto_02_Cocina.jpg', 'alt' => 'Cocina'],
                    ['img' => 'images/inn/proyecto/Proyecto_03_Living-Comedor.jpg', 'alt' => 'Living Comedor'],
                    ['img' => 'images/inn/proyecto/Proyecto_04_Vista-Acceso.jpg', 'alt' => 'Vista Acceso'],
                    ['img' => 'images/inn/proyecto/Proyecto_05_Isla.jpg', 'alt' => 'Isla Cocina'],
                    ['img' => 'images/inn/proyecto/Proyecto_06_Dorm-Ppal.jpg', 'alt' => 'Dormitorio Principal'],
                ],

                // Banners de Video Intermedios
                'video_interstitials' => [
                    ['text' => 'Un estilo de vida único frente al lago y los volcanes', 'video_url' => 'video/video_reconfortante_a.mp4'],
                    ['text' => 'Descubre todo lo que INN tiene para ofrecer', 'video_url' => 'video/exterior.mp4'],
                    ['text' => 'Despierta tu espíritu aventurero en la Región de Los Lagos', 'video_url' => 'video/lipsum.mp4'],
                ],

                // Sección 2: Equipamiento
                'equipment_eyebrow' => 'Equipamiento',
                'equipment_title' => "SOFISTICACIÓN\nEN CADA DETALLE",
                'equipment_description' => '<p>Equipamiento y terminaciones de alto estándar: cocina Franke con refrigerador y lavavajillas panelables, cubiertas ultracompactas en terminación travertino, griferías Paini y Hansgrohe, puertas enchapadas en encina, piso vinílico SPC y ventanas termopanel PVC negras.</p><p>Las terrazas incorporan revestimiento parcial de tejas, mientras cada departamento cuenta con calefacción por radiadores y caldera individual a gas natural.</p>',
                'equipment_bg_image_url' => 'images/inn/Perspectiva.svg',
                'equipment_brands' => [
                    ['src' => 'images/logos/franke.png', 'alt' => 'Franke'],
                    ['src' => 'images/logos/mk.png', 'alt' => 'MK'],
                    ['src' => 'images/logos/paini.png', 'alt' => 'Paini'],
                    ['src' => 'images/logos/hansgrohe.png', 'alt' => 'Hansgrohe'],
                ],
                'equipment_slides' => [
                    ['img' => 'images/inn/equipamiento/Equipamiento_Principal_Hall_Acceso.jpg', 'alt' => 'Hall de acceso'],
                    ['img' => 'images/inn/equipamiento/Equipamiento_Principal_Cocina.jpg', 'alt' => 'Cocina'],
                    ['img' => 'images/inn/equipamiento/Equipamiento_Principal_Living-Comedor.jpg', 'alt' => 'Living Comedor'],
                    ['img' => 'images/inn/equipamiento/Equipamiento_Principal_Terraza.jpg', 'alt' => 'Terraza'],
                    ['img' => 'images/inn/equipamiento/Equipamiento_Principal_Dormitorios.jpg', 'alt' => 'Dormitorio Principal'],
                    ['img' => 'images/inn/equipamiento/Equipamiento_Principal_Banos.jpg', 'alt' => 'Baño'],
                ],
                'equipment_spaces_modal' => [
                    [
                        'label' => 'Hall de acceso',
                        'images' => [
                            ['img' => 'images/inn/equipamiento/Equipamiento_Principal_Hall_Acceso.jpg', 'alt' => 'Hall de acceso', 'thumb' => 'images/inn/equipamiento/Equipamiento_Principal_Hall_Acceso.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Acceso_Cerradura_Digital.jpg', 'alt' => 'Puerta de acceso con cerradura digital', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Acceso_Cerradura_Digital.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Acceso_Piso_SPC.jpg', 'alt' => 'Piso vinilico SCP', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Acceso_Piso_SPC.jpg'],
                        ],
                    ],
                    [
                        'label' => 'Cocina',
                        'images' => [
                            ['img' => 'images/inn/equipamiento/Equipamiento_Principal_Cocina.jpg', 'alt' => 'Cocina', 'thumb' => 'images/inn/equipamiento/Equipamiento_Principal_Cocina.jpg'],
                            ['img' => 'images/inn/equipamiento/cocina/Equipamiento_Detalle_Cocina_Cubierta.jpg', 'alt' => 'Cubierta ultracompacta terminación travertino'],
                            ['img' => 'images/inn/equipamiento/cocina/Equipamiento_Detalle_Cocina_Refrigerador.jpg', 'alt' => 'Refrigerador panelado Franke'],
                            ['img' => 'images/inn/equipamiento/cocina/Equipamiento_Detalle_Cocina_Lavavajillas.jpg', 'alt' => 'Lavavajillas panelado Franke'],
                            ['img' => 'images/inn/equipamiento/cocina/Equipamiento_Detalle_Cocina_Encimera.jpg', 'alt' => 'Encimera de inducción Franke'],
                            ['img' => 'images/inn/equipamiento/cocina/Equipamiento_Detalle_Microondas_Horno_Franke.jpg', 'alt' => 'Microondas y horno empotrados Franke'],
                            ['img' => 'images/inn/equipamiento/cocina/Equipamiento_Detalle_Cocina_Griferia_Paini.jpg', 'alt' => 'Grifería italiana Paini'],
                        ],
                    ],
                    [
                        'label' => 'Living Comedor',
                        'images' => [
                            ['img' => 'images/inn/equipamiento/Equipamiento_Principal_Living-Comedor.jpg', 'alt' => 'Living comedor', 'thumb' => 'images/inn/equipamiento/Equipamiento_Principal_Living-Comedor.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Liv-Com_Puertas_Interiores.jpg', 'alt' => 'Puertas enchapadas en madera de encina', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Liv-Com_Puertas_Interiores.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Liv-Com_Iluminación_Ventanales.jpg', 'alt' => 'Iluminación incluida ventanas de termopanel de PVC', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Liv-Com_Iluminación_Ventanales.jpg'],
                        ],
                    ],
                    [
                        'label' => 'Terraza',
                        'images' => [
                            ['img' => 'images/inn/equipamiento/Equipamiento_Principal_Terraza.jpg', 'alt' => 'Terraza', 'thumb' => 'images/inn/equipamiento/Equipamiento_Principal_Terraza.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Terraza_Pavimento.jpg', 'alt' => 'Terraza con pavimento Gres', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Terraza_Pavimento.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Terraza_Tejuelas.jpg', 'alt' => 'Muros exteriores con revestimiento de tejuelas en madera nativa', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Terraza_Tejuelas.jpg'],
                        ],
                    ],
                    [
                        'label' => 'Dormitorios',
                        'images' => [
                            ['img' => 'images/inn/equipamiento/Equipamiento_Principal_Dormitorios.jpg', 'alt' => 'Dormitorio principal', 'thumb' => 'images/inn/equipamiento/Equipamiento_Principal_Dormitorios.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Dormitorios_Wallkincloset.jpg', 'alt' => 'Walk in closet', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Dormitorios_Wallkincloset.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Dormitorios_Dorm_2.jpg', 'alt' => 'Dormitorio 2', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Dormitorios_Dorm_2.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Dormitorios_Dorm_3.jpg', 'alt' => 'Dormitorio 3', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Dormitorios_Dorm_3.jpg'],
                        ],
                    ],
                    [
                        'label' => 'Baños',
                        'images' => [
                            ['img' => 'images/inn/equipamiento/Equipamiento_Principal_Banos.jpg', 'alt' => 'Baño principal', 'thumb' => 'images/inn/equipamiento/Equipamiento_Principal_Banos.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Baño_Mampara.jpg', 'alt' => 'Mampara vidrio templado en baños', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Baño_Mampara.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Baño_Grifería_Hansgrohe.jpg', 'alt' => 'Grifería Hansgrohe en baño principal', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Baño_Grifería_Hansgrohe.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Baño_Ducha_Hansgrohe.jpg', 'alt' => 'Columna de ducha Hansgrohe en baño principal', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Baño_Ducha_Hansgrohe.jpg'],
                            ['img' => 'images/inn/equipamiento/Equipamiento_Detalle_Baño_Accesorios_MK.jpg', 'alt' => 'Accesorios marca MK', 'thumb' => 'images/inn/equipamiento/Equipamiento_Detalle_Baño_Accesorios_MK.jpg'],
                        ],
                    ],
                ],

                // Sección 3: Recorridos 360 & Masterplan
                'virtual_tour_matterport_url' => 'https://my.matterport.com/show/?m=hQ8Fm33FqFY&brand=0',
                'virtual_tour_360_url' => 'https://www.lanube360.com/ileben1/',
                'virtual_tour_video_url' => null,
                'brochure_pdf_url' => '/storage/inn/brochure-inn.pdf',
                'virtual_tours_config' => [
                    [
                        'label' => 'Masterplan',
                        'title' => 'Masterplan',
                        'images' => [
                            ['title' => 'Masterplan', 'src' => 'images/inn/masterplan inn.jpg'],
                            ['title' => 'Masterplan Primer Piso', 'src' => 'images/inn/masterplan-piso-1.jpg'],
                            ['title' => 'Masterplan Piso 10', 'src' => 'images/inn/masterplan-piso-10.jpg'],
                        ],
                    ],
                    [
                        'label' => 'Piloto 360',
                        'src' => 'https://my.matterport.com/show/?m=hQ8Fm33FqFY&brand=0',
                        'title' => 'Conoce tu próximo departamento',
                    ],
                    [
                        'label' => 'Vista 360',
                        'src' => 'https://www.lanube360.com/ileben1/',
                        'title' => 'En primera línea frente al lago',
                    ],
                ],

                // Sección 4: Espacios Comunes
                'espacios_eyebrow' => 'Espacios',
                'espacios_title' => "ESPACIOS DE\nOTRO NIVEL",
                'espacios_description' => '<p>Los espacios están concebidos como una extensión natural de tu departamento, donde la sensación hotelera se integra con la serenidad del entorno.</p><p>Cada ambiente ha sido cuidadosamente diseñado para enriquecer tu rutina diaria, ofreciendo espacios de encuentro, trabajo y descanso que combinan la calidez sureña, una delicada propuesta de interiorismo y vistas privilegiadas para disfrutar Puerto Varas al máximo.</p>',
                'espacios_bg_image_url' => 'images/inn/Climbing.svg',
                'espacios_items' => [
                    ['id' => 'hall', 'navId' => 'hall', 'label' => 'Hall de acceso', 'icon_key' => 'bell', 'img' => 'images/inn/eecc/EECC_Hall_Acceso_Edificio.jpg', 'link' => 'images/inn/eecc/EECC_Hall_Acceso_Edificio.jpg', 'alt' => 'Hall de acceso'],
                    ['id' => 'atrio', 'navId' => 'atrio', 'label' => 'Atrio con 7<br />pisos de altura', 'icon_key' => 'table', 'img' => 'images/inn/eecc/EECC_03.jpg', 'link' => 'images/inn/eecc/EECC_Atrio_2.png', 'alt' => 'Atrio'],
                    ['id' => 'gourmet', 'navId' => 'gourmet', 'label' => 'Sala gourmet con<br />quincho techado', 'icon_key' => 'chef-hat', 'img' => 'images/inn/eecc/EECC_01.jpg', 'link' => 'images/inn/eecc/EECC_Quincho_Gourmet.jpg', 'alt' => 'Gourmet + Quincho equipado'],
                    ['id' => 'training', 'navId' => 'training', 'label' => 'Training Zone', 'icon_key' => 'dumbbell', 'img' => 'images/inn/eecc/EECC_Training_Zone.jpg', 'link' => 'images/inn/eecc/EECC_Training_Zone.jpg', 'alt' => 'Training Zone'],
                    ['id' => 'jacuzzi', 'navId' => 'jacuzzi', 'label' => 'Rooftop con jacuzzi', 'icon_key' => 'hot-tub', 'img' => 'images/inn/eecc/EECC_Terraza_Jacuzzi.jpg', 'link' => 'images/inn/eecc/EECC_Terraza_Jacuzzi_2.jpg', 'alt' => 'Jacuzzi exterior'],
                    ['id' => 'piscina', 'navId' => 'piscina', 'label' => 'Piscina climatizada', 'icon_key' => 'waves-ladder', 'img' => 'images/inn/eecc/EECC_Piscina_Climatizada.jpg', 'link' => 'images/inn/eecc/EECC_Piscina_Climatizada.jpg', 'alt' => 'Piscina climatizada'],
                    ['id' => 'bodega', 'navId' => 'bodega', 'label' => 'Bodega náutica', 'icon_key' => 'kayak', 'img' => 'images/inn/eecc/EECC_02.jpg', 'link' => 'images/inn/eecc/EECC_Bodega_Nautica.jpg', 'alt' => 'Bodega náutica'],
                ],

                // Sección 5: Ubicación & Mapa
                'map_eyebrow' => 'Ubicación',
                'map_title' => "ntorno\nINNigualable",
                'map_description' => '<p>Despertar con el lago Llanquihue y los volcanes enmarcando el paisaje es solo el comienzo. A un paso de INN, la costanera invita a caminar, disfrutar un café de especialidad, navegar o vivir nuevas aventuras.</p><p>Puerto Varas ofrece también una reconocida gastronomía, calles con identidad y una conexión permanente con la naturaleza del sur de Chile. Desde aquí se abren rutas hacia Ensenada, Frutillar, Chiloé y algunas de las postales más inolvidables de la Región de Los Lagos. Una ubicación excepcional para integrar paisaje, gastronomía, aventura y vida al aire libre día a día.</p>',
                'map_logo_url' => 'images/inn/E_titulo.png',
                'map_btn_text' => 'Experiencias',
                'map_address' => 'Vicente Pérez Rosales 991, Puerto Varas',
                'entorno_map_image_url' => 'images/inn/Mapa_Ok_Sitio.png',
                'map_pin_x' => 33.3,
                'map_pin_y' => 72.4,
                'map_pin_logo_url' => 'images/inn/pin_logo.png',
                'map_pois' => [
                    ['id' => 1, 'name' => 'Mesa Tropera', 'label' => 'MESA TROPERA', 'distance' => '1.2 km', 'category' => 'Gastronomía', 'time' => '12 minutos', 'img' => 'images/inn/ubicacion/Mesa_Tropera_AI.jpg', 'x' => 15.1, 'y' => 33.2, 'direction' => 'right'],
                    ['id' => 2, 'name' => 'Jumbo', 'label' => 'JUMBO', 'distance' => '1.5 km', 'category' => 'Supermercado', 'time' => '15 minutos', 'img' => 'images/inn/ubicacion/Supermercado_Jumbo_AI.jpg', 'x' => 66.9, 'y' => 55.1, 'direction' => 'right'],
                    ['id' => 3, 'name' => 'Casino Dreams', 'label' => 'CASINO DREAMS', 'distance' => '2.3 km', 'category' => 'Entretenimiento', 'time' => '22 minutos', 'img' => 'images/inn/ubicacion/Casino_Dreams_01_IA.jpg', 'x' => 15.9, 'y' => 46.9, 'direction' => 'right'],
                    ['id' => 4, 'name' => 'Cassis', 'label' => 'CASSIS', 'distance' => '2.5 km', 'category' => 'Cafetería', 'time' => '25 minutos', 'img' => 'images/inn/ubicacion/Cassis_IA.jpg', 'x' => 14.9, 'y' => 44.2, 'direction' => 'right'],
                    ['id' => 5, 'name' => 'Mall Paseo', 'label' => 'MALL', 'distance' => '3.1 km', 'category' => 'Comercio', 'time' => '30 minutos', 'img' => 'images/inn/ubicacion/Paseo_Puerto_Varas_AI.jpg', 'x' => 9.3, 'y' => 47.7, 'direction' => 'down'],
                    ['id' => 6, 'name' => 'La Olla', 'label' => 'LA OLLA', 'distance' => '3.8 km', 'category' => 'Gastronomía', 'time' => '20 minutos', 'img' => 'images/inn/ubicacion/La Olla_IA.jpg', 'x' => 63.4, 'y' => 75.6, 'direction' => 'right'],
                    ['id' => 7, 'name' => 'Costane', 'label' => 'Costanera', 'distance' => '1.0 km', 'category' => 'Interés', 'time' => '10 minutos', 'img' => 'images/inn/ubicacion/Costanera_2_IA.jpg', 'x' => 15.6, 'y' => 40.1, 'direction' => 'right'],
                ],

                // Sección 6: Interiorismo
                'interiorismo_eyebrow' => 'Interiorismo',
                'interiorismo_title' => "MAESTRÍA EN\nCADA DETALLE",
                'interiorismo_description' => '<p>El interiorismo de <b>Sofía Iturralde</b> y la iluminación de <b>Rafael Rivera</b> se unen en INN para crear una experiencia integral de diseño. Sofía aporta cerca de 20 años de trayectoria en proyectos inmobiliarios, corporativos y residenciales en Chile y Argentina. Rafael, especializado en iluminación en el Istituto Europeo di Design de Milán, desarrolla proyectos lumínicos desde 2003.</p><p>En <b>INN</b>, ambas disciplinas dialogan para que materiales, texturas, volúmenes y distintas escenas de luz construyan ambientes cálidos y sofisticados, conectados con la identidad del sur. Una propuesta que realza cada espacio y refuerza el carácter exclusivo de un proyecto pensado para vivir Puerto Varas desde el diseño, el confort y el bienestar.</p>',
                'interiorismo_bg_image_url' => 'images/inn/Interiorismo.svg',
                'designers' => [
                    [
                        'name' => 'Sofía Iturralde<br/><small>Interiorista</small>',
                        'image' => 'images/inn/Foto_Iturralde.jpg',
                        'alt' => 'Diseño de Sofía Iturralde',
                        'color' => '#d9bc70',
                    ],
                    [
                        'name' => 'Rafael Rivera<br/><small>Iluminador</small>',
                        'image' => 'images/inn/Foto_Rivera.jpg',
                        'alt' => 'Diseño de Rafael Rivera',
                        'color' => '#b78d56',
                    ],
                ],

                // Modal Panoramas
                'panoramas_categories' => [
                    [
                        'id' => 'ruta-del-lago',
                        'name' => 'Ruta del lago',
                        'tabLabel' => 'RUTA DEL LAGO',
                        'slides' => [
                            ['id' => 'ensenada', 'title' => 'Ensenada', 'distance' => '40 km', 'image' => 'images/inn/panoramas/Ensenada_04_IA.jpg'],
                            ['id' => 'playa-venado', 'title' => 'Playa Venado, Ruta 225, km 16', 'distance' => '14 km', 'image' => 'images/inn/panoramas/Fundo Playa Venado_2.jpg'],
                            ['id' => 'once-bellavista', 'title' => 'Onces Bellavista', 'distance' => '32 km', 'image' => 'images/inn/panoramas/Onces_Bellavista_IA.jpg'],
                        ],
                    ],
                    [
                        'id' => 'naturaleza-en-grande',
                        'name' => 'Naturaleza en grande',
                        'tabLabel' => 'NATURALEZA EN GRANDE',
                        'slides' => [
                            ['id' => 'saltos-del-petrohue', 'title' => 'Saltos del Petrohué', 'distance' => '53 km', 'image' => 'images/inn/panoramas/Saltos_del_Petrohue_AI.jpg'],
                            ['id' => 'todos-los-santos', 'title' => 'Lago Todos los Santos', 'distance' => '59 km', 'image' => 'images/inn/panoramas/Lago_Todos_los Santos_AI.jpg'],
                            ['id' => 'volcan-osorno', 'title' => 'Volcán Osorno, Centro de Sky', 'distance' => '57 km', 'image' => 'images/inn/panoramas/Centro de Sky.jpg'],
                            ['id' => 'volcan-calbuco', 'title' => 'Volcán Calbuco, Reserva Nacional Llanquihue', 'distance' => '60 km', 'image' => 'images/inn/panoramas/Volcan_Calbuco_AI.jpg'],
                        ],
                    ],
                    [
                        'id' => 'cultura-junto-al-lago',
                        'name' => 'Cultura junto al lago',
                        'tabLabel' => 'CULTURA JUNTO AL LAGO',
                        'slides' => [
                            ['id' => 'muelle-frutillar', 'title' => 'Muelle de Frutillar', 'distance' => '34 km', 'image' => 'images/inn/panoramas/Muelle_Frutillar_AI.jpg'],
                            ['id' => 'futrillar-teatro-del-lago', 'title' => 'Frutillar, Teatro del Lago', 'distance' => '32 km', 'image' => 'images/inn/panoramas/Teatro_del_Lago_AI.jpg'],
                            ['id' => 'futrillar-museo-colonial', 'title' => 'Frutillar, Museo Colonial Alemán', 'distance' => '32 km', 'image' => 'images/inn/panoramas/Museo Aleman.jpg'],
                            ['id' => 'casco-historico-puerto-octay', 'title' => 'Casco histórico de Puerto Octay', 'distance' => '55 km', 'image' => 'images/inn/panoramas/Casco_historico_Puerto_Octay.jpg'],
                        ],
                    ],
                    [
                        'id' => 'escapadas-memorables',
                        'name' => 'Escapadas memorables',
                        'tabLabel' => 'ESCAPADAS MEMORABLES',
                        'slides' => [
                            ['id' => 'cruce-a-chiloé-pargua', 'title' => 'Cruce a Chiloé, Pargua', 'distance' => '76 km', 'image' => 'images/inn/panoramas/Cruce_Chiloe_Pargua_AI.jpg'],
                            ['id' => 'isla-chiloe', 'title' => 'Chiloé', 'distance' => '115 km', 'image' => 'images/inn/panoramas/Iglesia_Chiloe_AI.jpg'],
                            ['id' => 'termas-del-sol', 'title' => 'Termas del Sol', 'distance' => '127 km', 'image' => 'images/inn/panoramas/Termas_del_Sol_AI.jpg'],
                        ],
                    ],
                ],

                // Sección 7: Sala de Ventas & Contacto
                'contact_eyebrow' => 'Contacto',
                'contact_title' => 'Conversemos',
                'contact_subtitle' => '<b>Sala de ventas y piloto:</b> Vicente Pérez Rosales 991, Puerto Varas.<br /><b>Horario:</b> Lun a Dom. 10:00 a 14:00 y 15:00 a 19:00 horas.',
                'contact_waze_url' => 'https://embed.waze.com/es/iframe?zoom=16&lat=-41.326080&lon=-72.970514&ct=livemap&pin=1&desc=0',
            ]
        );

        // Relational Amenities (compatibilidad)
        ProjectAmenity::where('project_detail_id', $detail->id)->delete();
        $amenities = [
            ['name' => 'Hall de acceso', 'icon_key' => 'bell', 'image_url' => 'images/inn/eecc/EECC_Hall_Acceso_Edificio.jpg', 'sort_order' => 1],
            ['name' => 'Bodega náutica', 'icon_key' => 'kayak', 'image_url' => 'images/inn/eecc/EECC_Bodega_Nautica.jpg', 'sort_order' => 2],
            ['name' => 'Piscina & Hot Tub', 'icon_key' => 'hot-tub', 'image_url' => 'images/inn/eecc/EECC_Piscina_Climatizada.jpg', 'sort_order' => 3],
            ['name' => 'Gimnasio Equipado', 'icon_key' => 'dumbbell', 'image_url' => 'images/inn/eecc/EECC_Training_Zone.jpg', 'sort_order' => 4],
            ['name' => 'Quincho Gourmet', 'icon_key' => 'chef-hat', 'image_url' => 'images/inn/eecc/EECC_Quincho_Gourmet.jpg', 'sort_order' => 5],
        ];
        foreach ($amenities as $item) {
            ProjectAmenity::create(array_merge($item, ['project_detail_id' => $detail->id]));
        }

        // Relational POIs (compatibilidad)
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
                'image_url' => 'images/inn/ubicacion/Mesa_Tropera_AI.jpg',
                'sort_order' => 1,
            ],
            [
                'name' => 'Supermercado Jumbo',
                'category' => 'Supermercado',
                'distance' => '1.5 km',
                'walking_time' => '15 minutos',
                'coordinate_x' => 66.9,
                'coordinate_y' => 55.1,
                'pin_direction' => 'right',
                'image_url' => 'images/inn/ubicacion/Supermercado_Jumbo_AI.jpg',
                'sort_order' => 2,
            ],
            [
                'name' => 'Muelle Histórico',
                'category' => 'Interés',
                'distance' => '1.0 km',
                'walking_time' => '10 minutos',
                'coordinate_x' => 15.6,
                'coordinate_y' => 40.1,
                'pin_direction' => 'right',
                'image_url' => 'images/inn/ubicacion/Costanera_2_IA.jpg',
                'sort_order' => 3,
            ],
        ];
        foreach ($pois as $item) {
            ProjectPoi::create(array_merge($item, ['project_detail_id' => $detail->id]));
        }

        // Relational Galleries (compatibilidad)
        ProjectGallery::where('project_detail_id', $detail->id)->delete();
        $galleries = [
            ['category' => 'bottom', 'title' => 'Vista fachada lago', 'image_url' => 'images/inn/galeria1.jpg', 'alt_text' => 'Fachada lago', 'sort_order' => 1],
            ['category' => 'bottom', 'title' => 'Living y terraza', 'image_url' => 'images/inn/galeria2.jpg', 'alt_text' => 'Living amplio', 'sort_order' => 2],
        ];
        foreach ($galleries as $item) {
            ProjectGallery::create(array_merge($item, ['project_detail_id' => $detail->id]));
        }
    }
}
