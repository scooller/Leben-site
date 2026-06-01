<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogViewNestedPropertiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_view_renders_when_properties_contain_nested_arrays(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['user_type' => 'admin']);

        $activity = Activity::query()->create([
            'log_name' => 'admin_actions',
            'description' => 'Registro con arrays anidados',
            'event' => 'updated',
            'causer_type' => User::class,
            'causer_id' => $admin->getKey(),
            'properties' => [
                'attributes' => [
                    'name' => 'Nuevo Nombre',
                    'meta' => [
                        'channels' => ['web', 'email'],
                        'flags' => [
                            'featured' => true,
                        ],
                    ],
                ],
                'old' => [
                    'name' => 'Nombre Anterior',
                    'meta' => [
                        'channels' => ['web'],
                    ],
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.activity-logs.view', ['record' => $activity]))
            ->assertOk();
    }
}
