<?php

namespace Database\Seeders;

use App\Models\Proyecto;
use Illuminate\Database\Seeder;

class ProjectSortOrderSeeder extends Seeder
{
    public function run(): void
    {
        // Orden canónico histórico de PROJECT_DISPLAY_ORDER en frontend React
        $preferredOrder = [
            'baum' => 1,
            'mood' => 2,
            'livingtown' => 3,
            'argomedo' => 4,
            'santo' => 5,
            'indi' => 6,
            'own' => 7,
            'suecia' => 8,
            'okinawa' => 9,
            'inn' => 10,
        ];

        foreach ($preferredOrder as $slug => $order) {
            Proyecto::query()
                ->where('slug', $slug)
                ->update(['sort_order' => $order]);
        }
    }
}
