<?php

namespace App\Jobs;

use App\Filament\Actions\SyncPlantsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class SyncPlantsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        try {
            Forrest::authenticate();
        } catch (\Exception $e) {
            Log::warning('SyncPlantsJob: Forrest authentication warning: '.$e->getMessage());
        }

        $result = SyncPlantsAction::execute();

        if ($result['success']) {
            Log::debug('SyncPlantsJob: '.$result['message']);
        } else {
            Log::error('SyncPlantsJob: '.$result['message']);
        }
    }
}
