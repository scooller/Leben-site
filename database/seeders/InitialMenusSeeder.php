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
                    ['label' => 'Quiénes Somos', 'url' => '/nosotros', 'target' => '_self', 'is_featured' => false, 'sort_order' => 1],
                    ['label' => 'Proceso de Reserva Online', 'url' => '/proceso-reserva-en-linea', 'target' => '_self', 'is_featured' => false, 'sort_order' => 2],
                    ['label' => 'Inversionistas Leben Invest', 'url' => '/invest', 'target' => '_self', 'is_featured' => false, 'sort_order' => 3],
                    ['label' => 'Círculo Brokers', 'url' => '/brokers', 'target' => '_self', 'is_featured' => false, 'sort_order' => 4],
                    ['label' => 'Canal de Denuncias', 'url' => 'https://canaldenuncias.cl/leben', 'target' => '_blank', 'is_featured' => false, 'sort_order' => 5],
                ],
            ],
            'footer_legal' => [
                'name' => 'Pie de Página Legal',
                'items' => [
                    ['label' => 'Bases Legales y Promociones', 'url' => '/bases-legales', 'target' => '_self', 'is_featured' => false, 'sort_order' => 1],
                    ['label' => 'Información de la Empresa', 'url' => '/informacion-de-la-empresa', 'target' => '_self', 'is_featured' => false, 'sort_order' => 2],
                    ['label' => 'Política de Privacidad y Cookies', 'url' => '/cookies', 'target' => '_self', 'is_featured' => false, 'sort_order' => 3],
                ],
            ],
        ];

        foreach ($menus as $location => $menuData) {
            $menu = Menu::query()->firstOrCreate(
                ['location' => $location],
                ['name' => $menuData['name']]
            );

            foreach ($menuData['items'] as $itemData) {
                MenuItem::query()->firstOrCreate(
                    ['menu_id' => $menu->id, 'url' => $itemData['url']],
                    array_merge($itemData, ['menu_id' => $menu->id, 'is_active' => true])
                );
            }
        }
    }
}
