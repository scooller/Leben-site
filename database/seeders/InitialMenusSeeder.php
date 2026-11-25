<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class InitialMenusSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            'header_main' => [
                'name' => 'Cabecera Principal',
                'items' => [
                    ['label' => 'Invest', 'url' => '/invest', 'target' => '_self', 'is_featured' => false, 'sort_order' => 1],
                    ['label' => 'Brokers', 'url' => '/brokers', 'target' => '_self', 'is_featured' => false, 'sort_order' => 2],
                    ['label' => 'Cotizar', 'url' => '/cotizador#cotizador-gral', 'target' => '_self', 'is_featured' => true, 'sort_order' => 3],
                    ['label' => 'Clientes', 'url' => '/login', 'target' => '_self', 'is_featured' => false, 'sort_order' => 4],
                ],
            ],
            'header_dropdown' => [
                'name' => 'Menú Desplegable de Cabecera',
                'items' => [
                    ['label' => 'Proyectos Santiago', 'url' => '/proyectos?region=metropolitana', 'target' => '_self', 'is_featured' => false, 'sort_order' => 1],
                    ['label' => 'Proyectos Sur de Chile', 'url' => '/proyectos?region=los-lagos', 'target' => '_self', 'is_featured' => false, 'sort_order' => 2],
                    ['label' => 'Quiénes Somos', 'url' => '/nosotros', 'target' => '_self', 'is_featured' => false, 'sort_order' => 3],
                    ['label' => 'Bases Legales', 'url' => '/bases-legales', 'target' => '_self', 'is_featured' => false, 'sort_order' => 4],
                    ['label' => 'Trabaja en Leben', 'url' => '/trabaja-en-leben', 'target' => '_self', 'is_featured' => false, 'sort_order' => 5],
                ],
            ],
            'footer_corporate' => [
                'name' => 'Pie de Página Corporativo',
                'items' => [
                    ['label' => 'Trabaja en Leben', 'url' => '/trabaja-en-leben', 'target' => '_self', 'is_featured' => false, 'sort_order' => 1],
                    ['label' => 'Bases legales', 'url' => '/bases-legales', 'target' => '_self', 'is_featured' => false, 'sort_order' => 2],
                    ['label' => 'Proceso de Reserva en línea', 'url' => '/proceso-reserva-en-linea', 'target' => '_self', 'is_featured' => false, 'sort_order' => 3],
                ],
            ],
            'footer_legal' => [
                'name' => 'Pie de Página Legal',
                'items' => [
                    ['label' => 'Información de la empresa', 'url' => '/informacion-de-la-empresa', 'target' => '_self', 'is_featured' => false, 'sort_order' => 1],
                    ['label' => 'Política de cookies', 'url' => '/cookies', 'target' => '_self', 'is_featured' => false, 'sort_order' => 2],
                    ['label' => 'Canal de Denuncias y Consultas', 'url' => 'https://denuncias.ileben.cl/', 'target' => '_blank', 'is_featured' => false, 'sort_order' => 3],
                ],
            ],
        ];

        foreach ($menus as $location => $menuData) {
            $menu = Menu::query()->firstOrCreate(
                ['location' => $location],
                ['name' => $menuData['name']]
            );

            $activeUrls = [];
            foreach ($menuData['items'] as $itemData) {
                $activeUrls[] = $itemData['url'];
                MenuItem::query()->updateOrCreate(
                    ['menu_id' => $menu->id, 'url' => $itemData['url']],
                    array_merge($itemData, ['menu_id' => $menu->id, 'is_active' => true])
                );
            }
            MenuItem::query()->where('menu_id', $menu->id)->whereNotIn('url', $activeUrls)->delete();
        }
    }
}
