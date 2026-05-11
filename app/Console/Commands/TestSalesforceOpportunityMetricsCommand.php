<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class TestSalesforceOpportunityMetricsCommand extends Command
{
    protected $signature = 'salesforce:test-opportunity-metrics {--limit=20 : Cantidad de oportunidades a consultar}';

    protected $description = 'Consulta oportunidades en Salesforce (REST directo) para validar campos Broker/Proyecto usados en métricas.';

    public function handle(): int
    {
        $credentials = config('forrest.credentials', []);

        $loginUrl = rtrim((string) ($credentials['loginURL'] ?? ''), '/');
        $clientId = (string) ($credentials['consumerKey'] ?? '');
        $clientSecret = (string) ($credentials['consumerSecret'] ?? '');
        $username = (string) ($credentials['username'] ?? '');
        $password = (string) ($credentials['password'] ?? '');

        if ($loginUrl === '' || $clientId === '' || $clientSecret === '' || $username === '' || $password === '') {
            $this->error('Faltan credenciales Salesforce en configuración (forrest.credentials).');

            return self::FAILURE;
        }

        $limit = max(1, min((int) $this->option('limit'), 200));

        try {
            $tokenResponse = Http::asForm()->post($loginUrl . '/services/oauth2/token', [
                'grant_type' => 'password',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'username' => $username,
                'password' => $password,
            ]);

            if (! $tokenResponse->successful()) {
                $this->error('Error obteniendo token Salesforce: HTTP ' . $tokenResponse->status());
                $this->line($tokenResponse->body());

                return self::FAILURE;
            }

            $tokenPayload = $tokenResponse->json();
            $accessToken = (string) ($tokenPayload['access_token'] ?? '');
            $instanceUrl = rtrim((string) ($tokenPayload['instance_url'] ?? ''), '/');

            if ($accessToken === '' || $instanceUrl === '') {
                $this->error('Respuesta de token inválida: faltan access_token o instance_url.');

                return self::FAILURE;
            }

            $configuredVersion = trim((string) config('forrest.version', ''));
            $apiVersion = $configuredVersion !== '' ? $configuredVersion : null;

            if ($apiVersion === null) {
                $versionsResponse = Http::withToken($accessToken)->get($instanceUrl . '/services/data');

                if (! $versionsResponse->successful()) {
                    $this->error('No fue posible resolver versión API: HTTP ' . $versionsResponse->status());
                    $this->line($versionsResponse->body());

                    return self::FAILURE;
                }

                $versions = $versionsResponse->json();
                if (! is_array($versions) || $versions === []) {
                    $this->error('Salesforce no devolvió versiones de API disponibles.');

                    return self::FAILURE;
                }

                $latest = end($versions);
                $apiVersion = (string) ($latest['version'] ?? '');

                if ($apiVersion === '') {
                    $this->error('No se pudo determinar versión de API desde Salesforce.');

                    return self::FAILURE;
                }
            }

            $soql = 'SELECT Id, Name, Broker__c, Broker__r.Name, Proyecto__c, Proyecto__r.Name, StageName, IsWon, IsClosed, CreatedDate '
                . 'FROM Opportunity '
                . 'WHERE IsDeleted = false AND IsPrivate = false AND Proyecto__c != null '
                . 'ORDER BY CreatedDate DESC '
                . 'LIMIT ' . $limit;

            $queryResponse = Http::withToken($accessToken)->get($instanceUrl . '/services/data/v' . $apiVersion . '/query', [
                'q' => $soql,
            ]);

            if (! $queryResponse->successful()) {
                $this->error('Error en query SOQL: HTTP ' . $queryResponse->status());
                $this->line($queryResponse->body());

                return self::FAILURE;
            }

            $payload = $queryResponse->json();
            $records = is_array($payload['records'] ?? null) ? $payload['records'] : [];

            $rows = [];
            $missingBrokerLink = 0;
            $missingProjectLink = 0;

            foreach ($records as $record) {
                $brokerId = (string) ($record['Broker__c'] ?? '');
                $projectId = (string) ($record['Proyecto__c'] ?? '');

                if ($brokerId === '') {
                    $missingBrokerLink++;
                }

                if ($projectId === '') {
                    $missingProjectLink++;
                }

                $rows[] = [
                    (string) ($record['Id'] ?? '-'),
                    (string) ($record['Broker__c'] ?? '-'),
                    (string) ($record['Broker__r']['Name'] ?? '-'),
                    (string) ($record['Proyecto__c'] ?? '-'),
                    (string) ($record['Proyecto__r']['Name'] ?? '-'),
                    (string) ($record['StageName'] ?? '-'),
                    isset($record['IsWon']) ? ((bool) $record['IsWon'] ? 'true' : 'false') : '-',
                    isset($record['IsClosed']) ? ((bool) $record['IsClosed'] ? 'true' : 'false') : '-',
                    (string) ($record['CreatedDate'] ?? '-'),
                ];
            }

            $this->info('Query ejecutada con éxito. API v' . $apiVersion);
            $this->line('Total devuelto por Salesforce: ' . (string) ($payload['totalSize'] ?? count($records)));
            $this->line('Registros inspeccionados: ' . count($records));
            $this->line('Sin Broker__c: ' . $missingBrokerLink);
            $this->line('Sin Proyecto__c: ' . $missingProjectLink);

            if ($rows !== []) {
                $this->table(
                    ['Opportunity Id', 'Broker__c', 'Broker', 'Proyecto__c', 'Proyecto', 'Stage', 'IsWon', 'IsClosed', 'CreatedDate'],
                    $rows
                );
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Excepción durante prueba Salesforce: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
