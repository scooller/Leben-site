<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('marketing', 'web');
        Role::findOrCreate('cliente', 'web');

        User::query()
            ->whereIn('user_type', ['super_admin', 'super admin'])
            ->get()
            ->each(fn (User $user): User => tap($user, fn (User $model): User => $model->syncRoles(['super_admin'])));

        User::query()
            ->where('user_type', 'admin')
            ->get()
            ->each(fn (User $user): User => tap($user, fn (User $model): User => $model->syncRoles(['admin'])));

        User::query()
            ->where('user_type', 'marketing')
            ->get()
            ->each(fn (User $user): User => tap($user, fn (User $model): User => $model->syncRoles(['marketing'])));

        User::query()
            ->whereIn('user_type', ['customer', 'cliente'])
            ->get()
            ->each(fn (User $user): User => tap($user, fn (User $model): User => $model->syncRoles(['cliente'])));
    }
}
