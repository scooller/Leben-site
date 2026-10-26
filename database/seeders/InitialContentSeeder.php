<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class InitialContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            InitialContactChannelsSeeder::class,
            ProjectSortOrderSeeder::class,
            InitialSiteSettingsSeeder::class,
            InitialTestimonialsSeeder::class,
            InitialMenusSeeder::class,
            InitialPagesSeeder::class,
            InitialInnProjectDetailSeeder::class,
        ]);
    }
}
