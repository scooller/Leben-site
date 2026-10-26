<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSiteMenus;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\Menu;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiteMenusAndUserTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('marketing', 'web');
        Role::findOrCreate('broker', 'web');
        Role::findOrCreate('cliente', 'web');
    }

    public function test_user_model_supports_broker_type_and_helpers(): void
    {
        $broker = User::factory()->create([
            'user_type' => User::TYPE_BROKER,
        ]);
        $broker->assignRole('broker');

        $this->assertTrue($broker->isBroker());
        $this->assertFalse($broker->isAdmin());
        $this->assertFalse($broker->isMarketing());
        $this->assertFalse($broker->isCustomer());

        $customer = User::factory()->create(['user_type' => 'customer']);
        $this->assertTrue($customer->isCustomer());
        $this->assertFalse($customer->isBroker());
    }

    public function test_marketing_can_access_and_manage_testimonials(): void
    {
        $marketing = User::factory()->create(['user_type' => 'marketing']);
        $marketing->assignRole('marketing');

        $this->actingAs($marketing);

        $this->assertTrue(TestimonialResource::canViewAny());
        $this->assertTrue(TestimonialResource::canCreate());

        $testimonial = Testimonial::create([
            'name' => 'Juan Pérez',
            'role' => 'Comprador Edificio INN',
            'rating' => 5.0,
            'review_text' => 'Excelente experiencia de compra',
            'is_published' => true,
        ]);

        $this->assertTrue(TestimonialResource::canEdit($testimonial));
        $this->assertTrue(TestimonialResource::canDelete($testimonial));

        $this->get('/admin/testimonials')->assertSuccessful();
    }

    public function test_marketing_can_access_and_save_menus_in_manage_site_menus_page(): void
    {
        $marketing = User::factory()->create(['user_type' => 'marketing']);
        $marketing->assignRole('marketing');

        $this->actingAs($marketing);

        $this->assertTrue(ManageSiteMenus::canAccess());
        $this->get('/admin/manage-site-menus')->assertSuccessful();

        Livewire::test(ManageSiteMenus::class)
            ->assertSuccessful()
            ->set('data.header_main', [
                [
                    'label' => 'Proyectos Nuevos',
                    'url' => '/proyectos',
                    'target' => '_self',
                    'badge' => 'Destacado',
                    'is_featured' => true,
                    'is_active' => true,
                ],
                [
                    'label' => 'Nosotros',
                    'url' => '/nosotros',
                    'target' => '_self',
                    'badge' => null,
                    'is_featured' => false,
                    'is_active' => true,
                ],
            ])
            ->set('data.footer_corporate', [
                [
                    'label' => 'Trabaja en Leben',
                    'url' => '/trabaja-en-leben',
                    'target' => '_self',
                    'badge' => null,
                    'is_featured' => false,
                    'is_active' => true,
                ],
            ])
            ->call('save')
            ->assertNotified('Menús guardados exitosamente');

        $headerMenu = Menu::where('location', 'header_main')->first();
        $this->assertNotNull($headerMenu);
        $this->assertCount(2, $headerMenu->allItems);
        $this->assertEquals('Proyectos Nuevos', $headerMenu->allItems->first()->label);
        $this->assertEquals(0, $headerMenu->allItems->first()->sort_order);

        $footerMenu = Menu::where('location', 'footer_corporate')->first();
        $this->assertNotNull($footerMenu);
        $this->assertCount(1, $footerMenu->allItems);
        $this->assertEquals('Trabaja en Leben', $footerMenu->allItems->first()->label);
    }
}
