<?php

namespace Tests\Feature;

use App\Models\Proyecto;
use App\Services\ProjectImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectImageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_salesforce_portada_when_project_has_no_manual_image(): void
    {
        $portadaUrl = 'https://example.my.salesforce.com/services/data/v57.0/sobjects/Document/015XX0000000001AAA/Body';

        $proyecto = Proyecto::factory()->create([
            'project_image_id' => null,
            'salesforce_portada_url' => $portadaUrl,
        ]);

        $this->assertSame($portadaUrl, ProjectImageService::getProjectImageUrl($proyecto));
    }
}
