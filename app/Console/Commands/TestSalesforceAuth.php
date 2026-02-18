<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Omniphx\Forrest\Exceptions\MissingKeyException;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class TestSalesforceAuth extends Command
{
    protected $signature = 'salesforce:test-auth';

    protected $description = 'Prueba la autenticación con Salesforce';

    public function handle()
    {
        try {
            $this->info('Intentando autenticación con Salesforce...');

            // Forzar obtención del token usando UserPassword
            Forrest::authenticate();

            $this->info('✓ Token obtenido exitosamente');

            // Hacer una consulta de prueba simple
            $this->info('Haciendo una consulta SOQL de prueba...');

            $result = Forrest::query('SELECT COUNT() FROM Lead');

            $this->info('✓ Consulta exitosa');
            $this->info('Conexión con Salesforce funcionando correctamente');

            return Command::SUCCESS;

        } catch (MissingKeyException $e) {
            $this->error('✗ Error de configuración: '.$e->getMessage());
            $this->error('Verifica que SF_CONSUMER_KEY y SF_CONSUMER_SECRET estén configurados en .env');

            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('✗ Error: '.$e->getMessage());
            $this->error('Tipo: '.get_class($e));

            return Command::FAILURE;
        }
    }
}
