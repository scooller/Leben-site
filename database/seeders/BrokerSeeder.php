<?php

namespace Database\Seeders;

use App\Models\Broker;
use App\Models\BrokerAlliance;
use App\Models\BrokerCategory;
use App\Models\BrokerEvent;
use App\Models\BrokerGallery;
use App\Models\BrokerGalleryItem;
use App\Models\CuratorMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BrokerSeeder extends Seeder
{
    /**
     * Alliances logos from brokers.ileben.cl
     */
    private array $alliances = [
        ['name' => 'Gen Proactivo', 'url' => 'https://genproactivo.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/GEN-PROACTIVO.jpg'],
        ['name' => 'Houm', 'url' => 'https://houm.com', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/LOGO-HOUM.jpeg'],
        ['name' => 'Rentabilízate', 'url' => 'https://rentabilizate.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/LOGO-RENTABILIAZATE-e1768223298638.jpg'],
        ['name' => 'Tributa Inteligente', 'url' => 'https://tributainteligente.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/LOGO-TRIBUTA-INTELIGENTE.jpeg'],
        ['name' => 'Rila', 'url' => 'https://rila.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/RILA.webp'],
        ['name' => 'Tisser', 'url' => 'https://tisser.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/Tisser.jpg'],
        ['name' => 'Big Capital', 'url' => 'https://bigcapital.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/BIG-CAPITAL.jpg'],
        ['name' => 'Audiprop', 'url' => 'https://audiprop.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/LOGO-AUDIPROP.jpeg'],
        ['name' => 'Club Inversiones', 'url' => 'https://clubinversiones.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/LOGO-CLUB-INVERSIONES.jpeg'],
        ['name' => 'GCI Consultores', 'url' => 'https://gciconsultores.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/GCI-CONSULTORE.jpeg'],
        ['name' => 'Facebook', 'url' => 'https://facebook.com', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/Icono-Fadcebook-2018.png'],
        ['name' => 'Ksa', 'url' => 'https://ksa.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/Ksa.jpg'],
        ['name' => 'HT', 'url' => 'https://ht.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/LOGO-HT-Dorado-Fondo-Negro.png'],
        ['name' => 'Mi Inversión', 'url' => 'https://miinversion.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/Logo-Mi-Inversion.jpeg'],
        ['name' => 'V&M', 'url' => 'https://vym.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/logo-V_M-vertical-oficial-sin-fondo.png'],
        ['name' => 'Alval', 'url' => 'https://alval.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/LOGO_ALVAL_04.png'],
        ['name' => 'Alligare', 'url' => 'https://alligare.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/LogoAlligareGris-scaled.png'],
        ['name' => 'P360 Inmobiliario', 'url' => 'https://p360.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/Logo-p360Inmobiliario.jpg'],
        ['name' => 'Propiedades Centinela', 'url' => 'https://propiedadescentinela.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/Propiedades-centinela-scaled.jpg'],
        ['name' => 'DPS', 'url' => 'https://dps.cl', 'logo' => 'https://brokers.ileben.cl/wp-content/uploads/2026/01/LOGO-DPS-1.jpeg'],
    ];

    /**
     * Events gallery photos by year/slug
     */
    private array $galleries = [
        [
            'title' => '2025',
            'year' => 2025,
            'month' => 7,
            'photos' => [
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/1-broker.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2-broker.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/3-broker.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/4-broker.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/5-broker.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/8-broker.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/7-broker.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/6-broker.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/Equipo-Broker-2025.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2025-1.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2025-2.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2025-3.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2025-4.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2025-5.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2025-6.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2025-7.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2025-8.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2025-9.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2025-10.jpg',
            ],
        ],
        [
            'title' => '2024',
            'year' => 2024,
            'month' => 7,
            'photos' => [
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2024-2-a.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2024-3-a.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2024-6-a.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2024-7-a.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2024-8-a.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2024-9-a.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2024-10-a.jpg',
            ],
        ],
        [
            'title' => '2023',
            'year' => 2023,
            'month' => 7,
            'photos' => [
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/1-desayuno.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/2.-desayuno.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/3-desayuno-1.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/4-desayuno-1.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/5-desayuno.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/6-desayuno.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/7-desayuno-1-1.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/8-desayuno-1-1.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/9-desayuno.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/10-desayuno.jpg',
            ],
        ],
        [
            'title' => 'Nuevas Alianzas',
            'year' => 2025,
            'month' => 1,
            'photos' => [
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/nuevas-alianzas-1.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/nuevas-alianzas-2.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/nuevas-alianzas-3-1-1.jpg',
                'https://brokers.ileben.cl/wp-content/uploads/2025/07/nuevas-alianzas-4.jpg',
            ],
        ],
    ];

    /**
     * Broker events
     */
    private array $events = [
        [
            'title' => 'Encuentro Anual Círculo de Brokers Leben 2025',
            'description' => 'Presentación exclusiva de resultados, nuevas alianzas comerciales y plan de metas para el segundo semestre.',
            'starts_at' => '2025-07-20 09:30:00',
            'ends_at' => '2025-07-20 13:00:00',
            'location' => 'Santiago / Hotel W',
            'photo' => 'https://brokers.ileben.cl/wp-content/uploads/2025/07/Equipo-Broker-2025.jpg',
        ],
        [
            'title' => 'Lanzamiento Preventa Exclusiva Edificio INN',
            'description' => 'Presentación de tipologías exclusivas y condiciones especiales de comisión para brokers en el sur.',
            'starts_at' => '2025-04-15 18:30:00',
            'ends_at' => '2025-04-15 21:00:00',
            'location' => 'Puerto Varas',
            'photo' => 'https://brokers.ileben.cl/wp-content/uploads/2025/07/1-broker.jpg',
        ],
        [
            'title' => 'Capacitación Comercial & Gestión Hipotecaria',
            'description' => 'Taller intensivo para brokers sobre estrategias de aceleración de promesas y financiamiento bancario.',
            'starts_at' => '2025-09-10 10:00:00',
            'ends_at' => '2025-09-10 12:00:00',
            'location' => 'Online Streaming',
            'photo' => 'https://brokers.ileben.cl/wp-content/uploads/2025/07/2-broker.jpg',
        ],
        [
            'title' => 'Gala de Premiación Mejor Broker del Año',
            'description' => 'Reconocimiento anual a las mejores agencias del Círculo Leben y entrega del premio Punta Cana.',
            'starts_at' => '2025-12-15 19:30:00',
            'ends_at' => '2025-12-15 23:30:00',
            'location' => 'Santiago',
            'photo' => 'https://brokers.ileben.cl/wp-content/uploads/2025/07/3-broker.jpg',
        ],
    ];

    public function run(): void
    {
        // 1. Ensure categories and benefits are seeded
        if (BrokerCategory::count() === 0) {
            $this->call(BrokerCategorySeeder::class);
        }

        $defaultCategory = BrokerCategory::where('slug', 'partner-black')->first()
            ?? BrokerCategory::first();

        // 2. Ensure primary broker exists
        $broker = Broker::firstOrCreate(
            ['display_name' => 'Círculo Leben'],
            [
                'contact_email' => 'nicolas.munoz@ileben.cl',
                'contact_phone' => '+56 9 9129 7804',
                'broker_category_id' => $defaultCategory?->id,
                'is_active' => true,
                'sort_order' => 1,
                'notes' => 'Broker institucional principal Círculo Leben',
            ]
        );

        $this->command?->info("Broker principal configurado: {$broker->display_name} (ID: {$broker->id})");

        // 3. Seed Alliances
        foreach ($this->alliances as $index => $item) {
            $media = $this->resolveMedia($item['logo'], $item['name'], 'alliances');

            BrokerAlliance::updateOrCreate(
                [
                    'broker_id' => $broker->id,
                    'name' => $item['name'],
                ],
                [
                    'url' => $item['url'],
                    'image_id' => $media?->id,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
        $this->command?->info(count($this->alliances) . ' alianzas procesadas.');

        // 4. Seed Events
        foreach ($this->events as $eventData) {
            $media = $this->resolveMedia($eventData['photo'], $eventData['title'], 'events');

            BrokerEvent::updateOrCreate(
                [
                    'broker_id' => $broker->id,
                    'title' => $eventData['title'],
                ],
                [
                    'description' => $eventData['description'],
                    'starts_at' => Carbon::parse($eventData['starts_at']),
                    'ends_at' => Carbon::parse($eventData['ends_at']),
                    'location' => $eventData['location'],
                    'image_id' => $media?->id,
                    'is_published' => true,
                ]
            );
        }
        $this->command?->info(count($this->events) . ' eventos procesados.');

        // 5. Seed Galleries & Items
        foreach ($this->galleries as $galleryIndex => $galleryData) {
            $gallery = BrokerGallery::updateOrCreate(
                [
                    'broker_id' => $broker->id,
                    'title' => $galleryData['title'],
                ],
                [
                    'year' => $galleryData['year'],
                    'month' => $galleryData['month'],
                    'sort_order' => $galleryIndex + 1,
                    'is_published' => true,
                ]
            );

            foreach ($galleryData['photos'] as $photoIndex => $photoUrl) {
                $caption = "{$galleryData['title']} - Foto " . ($photoIndex + 1);
                $media = $this->resolveMedia($photoUrl, $caption, "galleries/{$galleryData['year']}");

                BrokerGalleryItem::updateOrCreate(
                    [
                        'broker_gallery_id' => $gallery->id,
                        'sort_order' => $photoIndex + 1,
                    ],
                    [
                        'image_id' => $media?->id,
                        'caption' => $caption,
                        'is_active' => true,
                    ]
                );
            }
        }
        $this->command?->info(count($this->galleries) . ' galerías procesadas.');
    }

    /**
     * Download or retrieve existing CuratorMedia for a remote URL
     */
    private function resolveMedia(string $remoteUrl, string $title, string $folder): ?CuratorMedia
    {
        $rawFilename = basename((string) parse_url($remoteUrl, PHP_URL_PATH));
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $rawFilename) ?: 'image.jpg';
        $directory = 'brokers/' . trim($folder, '/');
        $relativePath = $directory . '/' . $filename;
        $disk = Storage::disk('curator');

        // Check if media already exists in curator table
        $existing = CuratorMedia::where('path', $relativePath)->first();
        if ($existing) {
            return $existing;
        }

        // Download if file does not exist in local disk
        if (! $disk->exists($relativePath)) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                ])
                ->withoutVerifying()
                ->timeout(10)
                ->get($remoteUrl);

                if ($response->successful() && strlen($response->body()) > 0) {
                    $disk->put($relativePath, $response->body());
                }
            } catch (\Throwable $e) {
                // If network fails (offline / CI), continue gracefully
            }
        }

        // If file exists on disk, create CuratorMedia entry
        if ($disk->exists($relativePath)) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg');
            $fullPath = $disk->path($relativePath);
            $size = @filesize($fullPath) ?: 1000;
            $imageSize = @getimagesize($fullPath) ?: [800, 600];
            $mime = $imageSize['mime'] ?? ('image/' . ($ext === 'jpg' ? 'jpeg' : $ext));

            return CuratorMedia::create([
                'disk' => 'curator',
                'directory' => $directory,
                'visibility' => 'public',
                'name' => pathinfo($filename, PATHINFO_FILENAME),
                'path' => $relativePath,
                'width' => $imageSize[0] ?? 800,
                'height' => $imageSize[1] ?? 600,
                'size' => $size,
                'type' => $mime,
                'ext' => $ext,
                'title' => $title,
                'pretty_name' => $title,
            ]);
        }

        return null;
    }
}
