<?php

namespace Tests\Feature;

use App\Models\User;
use BinaryBuilds\CommandRunner\Models\CommandRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogAdminToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_api_token_creation_and_revocation(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($admin);

        Activity::query()->delete();

        $newToken = $admin->createToken('integration-test-token', ['*']);
        $newToken->accessToken->forceFill([
            'authorized_url' => 'https://example-client.test',
        ])->save();

        $tokenClass = get_class($newToken->accessToken);

        $createdActivity = Activity::query()
            ->where('log_name', 'admin_actions')
            ->where('description', 'Token API creado')
            ->where('subject_type', $tokenClass)
            ->where('subject_id', $newToken->accessToken->getKey())
            ->first();

        $this->assertNotNull($createdActivity);
        $this->assertSame($admin->getKey(), $createdActivity->causer_id);
        $this->assertSame('api_token_created', $createdActivity->properties['action']);

        $tokenId = $newToken->accessToken->getKey();
        $newToken->accessToken->delete();

        $deletedActivity = Activity::query()
            ->where('log_name', 'admin_actions')
            ->where('description', 'Token API revocado')
            ->where('subject_type', $tokenClass)
            ->where('subject_id', $tokenId)
            ->first();

        $this->assertNotNull($deletedActivity);
        $this->assertSame('api_token_revoked', $deletedActivity->properties['action']);
    }

    public function test_it_logs_command_runner_start_and_finish(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($admin);

        Activity::query()->delete();

        $commandRun = CommandRun::query()->create([
            'command' => 'php artisan queue:work --once',
            'ran_by' => $admin->getKey(),
            'started_at' => now()->toDateTimeString(),
        ]);

        $startedActivity = Activity::query()
            ->where('log_name', 'admin_actions')
            ->where('description', 'Command Runner ejecutado')
            ->where('subject_type', CommandRun::class)
            ->where('subject_id', $commandRun->getKey())
            ->first();

        $this->assertNotNull($startedActivity);
        $this->assertSame('command_runner_started', $startedActivity->properties['action']);

        $commandRun->forceFill([
            'completed_at' => now()->toDateTimeString(),
            'exit_code' => 0,
        ])->save();

        $finishedActivity = Activity::query()
            ->where('log_name', 'admin_actions')
            ->where('description', 'Command Runner finalizado')
            ->where('subject_type', CommandRun::class)
            ->where('subject_id', $commandRun->getKey())
            ->first();

        $this->assertNotNull($finishedActivity);
        $this->assertSame('command_runner_finished', $finishedActivity->properties['action']);
    }
}
