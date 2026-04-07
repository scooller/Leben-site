<?php

namespace Tests\Feature\Filament\Actions;

use App\Filament\Actions\EraseAllPlantsAction;
use App\Models\Plant;
use App\Models\PlantReservation;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EraseAllPlantsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_erase_all_plants_successfully_deletes_all_plants(): void
    {
        $project = Proyecto::factory()->create();
        Plant::factory()->count(5)->create(['salesforce_proyecto_id' => $project->salesforce_id]);

        Activity::query()->delete();

        self::assertCount(5, Plant::all());

        $result = EraseAllPlantsAction::execute();

        self::assertTrue($result['success']);
        self::assertEquals(5, $result['count']);
        self::assertCount(0, Plant::all());

        $activity = Activity::query()
            ->where('log_name', 'admin_actions')
            ->where('description', 'Eliminacion masiva de plants')
            ->first();

        self::assertNotNull($activity);
        self::assertSame(5, $activity->properties['deleted_count']);
        self::assertSame('mass_delete', $activity->properties['action']);
    }

    public function test_erase_all_plants_with_related_reservations(): void
    {
        $user = User::factory()->create();
        $project = Proyecto::factory()->create();
        $plants = Plant::factory()->count(3)->create(['salesforce_proyecto_id' => $project->salesforce_id]);

        foreach ($plants as $plant) {
            PlantReservation::query()->create([
                'plant_id' => $plant->id,
                'user_id' => $user->id,
                'session_token' => Str::random(64),
                'status' => 'active',
                'expires_at' => now()->addMinutes(15),
                'metadata' => null,
            ]);
        }

        self::assertCount(3, Plant::all());
        self::assertCount(3, PlantReservation::all());

        $result = EraseAllPlantsAction::execute();

        self::assertTrue($result['success']);
        self::assertEquals(3, $result['count']);
        self::assertCount(0, Plant::all());
        self::assertCount(0, PlantReservation::all());
    }

    public function test_erase_all_plants_returns_correct_count(): void
    {
        $project = Proyecto::factory()->create();
        Plant::factory()->count(10)->create(['salesforce_proyecto_id' => $project->salesforce_id]);

        $result = EraseAllPlantsAction::execute();

        self::assertEquals(10, $result['count']);
        self::assertStringContainsString('10', $result['message']);
    }

    public function test_erase_all_plants_on_empty_table(): void
    {
        $result = EraseAllPlantsAction::execute();

        self::assertTrue($result['success']);
        self::assertEquals(0, $result['count']);
        self::assertCount(0, Plant::all());
    }
}
