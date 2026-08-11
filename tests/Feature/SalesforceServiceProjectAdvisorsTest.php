<?php

namespace Tests\Feature;

use App\Services\Salesforce\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;
use Tests\TestCase;

class SalesforceServiceProjectAdvisorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Forrest::shouldReceive('hasToken')->andReturn(true)->byDefault();

        Cache::flush();
    }

    public function test_find_projects_includes_advisor_slots_and_merges_unique_ids(): void
    {
        Forrest::shouldReceive('query')
            ->once()
            ->withArgs(function (string $soql): bool {
                return str_contains($soql, 'Asesor_Responsable__c, Asesor_1__c, Asesor_2__c')
                    && str_contains($soql, 'FROM Proyecto__c');
            })
            ->andReturn([
                'records' => [
                    [
                        'Id' => 'a0J8c00000sdxCZEAY',
                        'Name' => 'Edificio INN',
                        'Descripci_n__c' => null,
                        'Direccion__c' => null,
                        'Comuna__c' => null,
                        'Provincia__c' => null,
                        'Region__c' => null,
                        'Email__c' => null,
                        'Telefono__c' => null,
                        'Pagina_Web_Proyecto__c' => null,
                        'Razon_Social__c' => null,
                        'RUT__c' => null,
                        'Fecha_Inicio_Ventas__c' => null,
                        'Fecha_Recepcion_Municipal__c' => null,
                        'Etapa__c' => null,
                        'Horario_Atencion__c' => null,
                        'Asesor_Responsable__c' => '0058c00000AqczfAAB',
                        'Asesor_1__c' => '0058c00000AqczfAAB',
                        'Asesor_2__c' => '0058c00000Aqd0YAAR',
                        'Valor_Reserva_Exigido_Defecto_Peso__c' => null,
                        'Valor_Reserva_Exigido_Min_Peso__c' => null,
                        'Entrega_Inmediata__c' => false,
                    ],
                ],
            ]);

        $projects = app(SalesforceService::class)->findProjects(0);

        $this->assertCount(1, $projects);
        $this->assertSame([
            '0058c00000AqczfAAB',
            '0058c00000Aqd0YAAR',
        ], $projects[0]['asesor_responsable_ids']);
    }
}
