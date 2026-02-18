<?php

namespace App\Console\Commands;

use App\Filament\Actions\SyncPlantsAction;
use Illuminate\Console\Command;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class SyncAllPlants extends Command
{
    protected $signature = 'sync:plants';

    protected $description = 'Sincronizar todas las plantas con proyectos';

    public function handle()
    {
        $this->info('🔄 Sincronizando plantas...');

        try {
            Forrest::authenticate();
        } catch (\Exception $e) {
            $this->warn("⚠️  Autenticación de Forrest: {$e->getMessage()}");
        }

        $result = SyncPlantsAction::execute();

        if ($result['success']) {
            $this->info("✅ {$result['message']}");
            $this->info("Creados: {$result['created']}, Actualizados: {$result['updated']}");
        } else {
            $this->error("❌ {$result['message']}");
        }

        return $result['success'] ? 0 : 1;
    }
}
