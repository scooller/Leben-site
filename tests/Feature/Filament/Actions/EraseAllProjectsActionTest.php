<?php

namespace Tests\Feature\Filament\Actions;

use App\Filament\Actions\EraseAllProjectsAction;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EraseAllProjectsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_erase_all_projects_successfully_deletes_all_projects(): void
    {
        Proyecto::factory()->count(4)->create();

        Activity::query()->delete();

        self::assertCount(4, Proyecto::all());

        $result = EraseAllProjectsAction::execute();

        self::assertTrue($result['success']);
        self::assertEquals(4, $result['count']);
        self::assertCount(0, Proyecto::all());

        $activity = Activity::query()
            ->where('log_name', 'admin_actions')
            ->where('description', 'Eliminacion masiva de proyectos')
            ->first();

        self::assertNotNull($activity);
        self::assertSame(4, $activity->properties['deleted_count']);
        self::assertSame('mass_delete', $activity->properties['action']);
    }

    public function test_erase_all_projects_on_empty_table(): void
    {
        $result = EraseAllProjectsAction::execute();

        self::assertTrue($result['success']);
        self::assertEquals(0, $result['count']);
    }
}
