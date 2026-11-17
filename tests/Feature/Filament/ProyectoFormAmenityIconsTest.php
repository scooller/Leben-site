<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Proyectos\Pages\EditProyecto;
use App\Models\Proyecto;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProyectoFormAmenityIconsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_espacios_items_icon_key_has_all_amenity_options(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($user);

        $proyecto = Proyecto::factory()->create();

        $livewire = Livewire::test(EditProyecto::class, ['record' => $proyecto->id]);
        $form = $livewire->instance()->form;

        // Search recursively for Select with name 'icon_key'
        $iconSelect = null;
        $search = function ($items) use (&$search, &$iconSelect) {
            foreach ($items as $item) {
                if ($item instanceof Select && $item->getName() === 'icon_key') {
                    $iconSelect = $item;

                    return;
                }
                if ($item instanceof Repeater) {
                    $search($item->getChildComponents());
                } elseif (method_exists($item, 'getChildComponents')) {
                    $search($item->getChildComponents());
                }
            }
        };

        $search($form->getComponents());

        $this->assertNotNull($iconSelect, 'Select icon_key must exist in ProyectoForm');
        $options = $iconSelect->getOptions();

        $expectedKeys = [
            'bell',
            'concierge-bell',
            'table',
            'chef-hat',
            'dumbbell',
            'hot-tub',
            'waves-ladder',
            'kayak',
            'flame-kindling',
            'heater',
            'shopping-cart',
            'waves-vertical',
            'cinema',
            'flame',
            'building',
            'leaf',
            'utensils',
            'coffee',
            'droplets',
            'bike',
            'fuel',
            'sofa',
            'fishing-hook',
            'microwave',
            'plant-pot',
            'groceries',
            'warehouse-waves',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $options, "Missing option key: {$key}");
        }
    }
}
