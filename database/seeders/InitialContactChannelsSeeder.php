<?php

namespace Database\Seeders;

use App\Models\ContactChannel;
use Illuminate\Database\Seeder;

class InitialContactChannelsSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            [
                'slug' => 'sale',
                'name' => 'Ventas y Cotizaciones',
                'slug_badge_color' => 'blue',
                'is_active' => true,
                'is_default' => true,
            ],
            [
                'slug' => 'broker',
                'name' => 'Postulaciones Brokers',
                'slug_badge_color' => 'amber',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'slug' => 'investor',
                'name' => 'Inversionistas Leben Invest',
                'slug_badge_color' => 'purple',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'slug' => 'job',
                'name' => 'Trabaja en Leben',
                'slug_badge_color' => 'emerald',
                'is_active' => true,
                'is_default' => false,
            ],
        ];

        foreach ($channels as $channelData) {
            ContactChannel::query()->firstOrCreate(
                ['slug' => $channelData['slug']],
                $channelData
            );
        }
    }
}
