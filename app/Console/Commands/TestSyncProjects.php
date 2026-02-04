<?php

namespace App\Console\Commands;

use App\Filament\Actions\SyncProjectsAction;
use Illuminate\Console\Command;

class TestSyncProjects extends Command
{
    protected $signature = 'test:sync-projects';
    protected $description = 'Test SyncProjectsAction';

    public function handle()
    {
        $this->info('🔄 Sincronizando proyectos...');
        
        $result = SyncProjectsAction::execute();
        
        if ($result['success']) {
            $this->info("✅ {$result['message']}");
            $this->info("Creados: {$result['created']}, Actualizados: {$result['updated']}");
        } else {
            $this->error("❌ {$result['message']}");
        }
        
        return $result['success'] ? 0 : 1;
    }
}
