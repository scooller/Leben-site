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
        // Crear o actualizar usuario administrador
        User::updateOrCreate(
            ['email' => 'admin@ileben.cl'],
            [
                'name' => 'Administrador',
                'user_type' => 'admin',
                'phone' => '+56 9 1234 5678',
                'rut' => '12.345.678-9',
                'password' => Hash::make('Admin123!'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✅ Usuario administrador creado/verificado:');
        $this->command->info('   Email: admin@ileben.cl');
        $this->command->info('   Password: Admin123!');
        $this->command->info('   Tipo: Administrador');
    }
}
