<?php

namespace Tests\Feature;

use App\Filament\Resources\FrontendPreviewLinks\Pages\ListFrontendPreviewLinks;
use App\Models\FrontendPreviewLink;
use App\Models\User;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FrontendPreviewLinkManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_revoke_preview_links(): void
    {
        $admin = User::factory()->create([
            'user_type' => 'admin',
        ]);

        $firstPreviewLink = FrontendPreviewLink::query()->create([
            'name' => 'preview-1',
            'token' => Str::random(64),
            'expires_at' => Carbon::now()->addDay(),
        ]);

        $secondPreviewLink = FrontendPreviewLink::query()->create([
            'name' => 'preview-2',
            'token' => Str::random(64),
            'expires_at' => Carbon::now()->addDay(),
        ]);

        $this->actingAs($admin);

        Livewire::test(ListFrontendPreviewLinks::class)
            ->assertCanSeeTableRecords([$firstPreviewLink, $secondPreviewLink])
            ->selectTableRecords([$firstPreviewLink, $secondPreviewLink])
            ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
            ->assertNotified()
            ->assertCanNotSeeTableRecords([$firstPreviewLink, $secondPreviewLink]);

        $this->assertDatabaseMissing('frontend_preview_links', [
            'id' => $firstPreviewLink->id,
        ]);

        $this->assertDatabaseMissing('frontend_preview_links', [
            'id' => $secondPreviewLink->id,
        ]);
    }

    public function test_expired_preview_links_older_than_one_day_are_pruned(): void
    {
        $oldPreviewLink = FrontendPreviewLink::query()->create([
            'name' => 'preview-old',
            'token' => Str::random(64),
            'expires_at' => Carbon::now()->subDays(2),
        ]);

        $recentPreviewLink = FrontendPreviewLink::query()->create([
            'name' => 'preview-recent',
            'token' => Str::random(64),
            'expires_at' => Carbon::now()->subHours(12),
        ]);

        Artisan::call('model:prune', [
            '--model' => [FrontendPreviewLink::class],
        ]);

        $this->assertDatabaseMissing('frontend_preview_links', [
            'id' => $oldPreviewLink->id,
        ]);

        $this->assertDatabaseHas('frontend_preview_links', [
            'id' => $recentPreviewLink->id,
        ]);
    }
}
