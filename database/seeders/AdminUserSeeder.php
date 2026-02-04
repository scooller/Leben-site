<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear usuario administrador si no existe
        User::firstOrCreate(
            ['email' => 'admin@ileben.cl'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Admin123!'),
            ]
        );

        $this->command->info('✅ Usuario administrador creado/verificado:');
        $this->command->info('   Email: admin@ileben.cl');
        $this->command->info('   Password: Admin123!');
    }
}
