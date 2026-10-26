<?php

namespace Tests\Feature;

use App\Filament\Resources\Plants\Schemas\PlantForm;
use App\Filament\Resources\Proyectos\Schemas\ProyectoForm;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component as LivewireComponent;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinancialValuesAndDiscountsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('marketing', 'web');
        Role::findOrCreate('cliente', 'web');
    }

    public function test_user_model_role_and_permission_methods(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $superAdmin = User::factory()->create(['user_type' => 'super_admin']);
        $superAdminByRole = User::factory()->create(['user_type' => 'customer']);
        $superAdminByRole->assignRole('super_admin');
        $marketing = User::factory()->create(['user_type' => 'marketing']);
        $customer = User::factory()->create(['user_type' => 'customer']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue($admin->canManageFinancialValues());

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($superAdmin->isAdmin());
        $this->assertTrue($superAdmin->canManageFinancialValues());

        $this->assertTrue($superAdminByRole->isSuperAdmin());
        $this->assertTrue($superAdminByRole->isAdmin());
        $this->assertTrue($superAdminByRole->canManageFinancialValues());

        $this->assertFalse($marketing->isAdmin());
        $this->assertFalse($marketing->isSuperAdmin());
        $this->assertFalse($marketing->canManageFinancialValues());

        $this->assertFalse($customer->isAdmin());
        $this->assertFalse($customer->isSuperAdmin());
        $this->assertFalse($customer->canManageFinancialValues());
    }

    public function test_proyecto_form_financial_fields_are_editable_by_admin_and_super_admin(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $superAdmin = User::factory()->create(['user_type' => 'super_admin']);

        foreach ([$admin, $superAdmin] as $user) {
            $this->actingAs($user);

            $schema = ProyectoForm::configure(Schema::make($this->makeSchemaHost()));
            $components = $schema->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true);

            $fields = [
                'valor_reserva_exigido_defecto_peso',
                'valor_reserva_exigido_min_peso',
                'descuento_defecto_cotizacion_web',
                'descuento_maximo_unidad',
                'descuento_iva',
            ];

            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $components, "Field {$field} missing in ProyectoForm");
                $this->assertFalse($components[$field]->isDisabled(), "Field {$field} should be editable by {$user->user_type}");
            }
        }
    }

    public function test_proyecto_form_financial_fields_are_disabled_for_non_admins(): void
    {
        $marketing = User::factory()->create(['user_type' => 'marketing']);
        $customer = User::factory()->create(['user_type' => 'customer']);

        foreach ([$marketing, $customer] as $user) {
            $this->actingAs($user);

            $schema = ProyectoForm::configure(Schema::make($this->makeSchemaHost()));
            $components = $schema->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true);

            $fields = [
                'valor_reserva_exigido_defecto_peso',
                'valor_reserva_exigido_min_peso',
                'descuento_defecto_cotizacion_web',
                'descuento_maximo_unidad',
                'descuento_iva',
            ];

            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $components, "Field {$field} missing in ProyectoForm");
                $this->assertTrue($components[$field]->isDisabled(), "Field {$field} should be disabled for {$user->user_type}");
            }
        }
    }

    public function test_plant_form_financial_fields_are_editable_by_admin_and_super_admin(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $superAdmin = User::factory()->create(['user_type' => 'super_admin']);

        foreach ([$admin, $superAdmin] as $user) {
            $this->actingAs($user);

            $schema = PlantForm::configure(Schema::make($this->makeSchemaHost()));
            $components = $schema->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true);

            $fields = [
                'precio_base',
                'precio_lista',
                'priorizar_descuentos',
                'descuento_defecto_cotizacion_web',
                'descuento_maximo_unidad',
                'descuento_iva',
            ];

            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $components, "Field {$field} missing in PlantForm");
                $this->assertFalse($components[$field]->isDisabled(), "Field {$field} should be editable by {$user->user_type}");
            }
        }
    }

    public function test_plant_form_financial_fields_are_disabled_for_non_admins(): void
    {
        $marketing = User::factory()->create(['user_type' => 'marketing']);
        $customer = User::factory()->create(['user_type' => 'customer']);

        foreach ([$marketing, $customer] as $user) {
            $this->actingAs($user);

            $schema = PlantForm::configure(Schema::make($this->makeSchemaHost()));
            $components = $schema->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true);

            $fields = [
                'precio_base',
                'precio_lista',
                'priorizar_descuentos',
                'descuento_defecto_cotizacion_web',
                'descuento_maximo_unidad',
                'descuento_iva',
            ];

            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $components, "Field {$field} missing in PlantForm");
                $this->assertTrue($components[$field]->isDisabled(), "Field {$field} should be disabled for {$user->user_type}");
            }
        }
    }

    private function makeSchemaHost(): HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            public function render()
            {
                return '<div></div>';
            }

            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(string $key, bool $withHidden = false, array $skipComponentsChildContainersWhileSearching = []): Component|Action|ActionGroup|null
            {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void {}

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }
}
