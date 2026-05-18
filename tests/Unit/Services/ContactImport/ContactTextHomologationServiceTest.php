<?php

namespace Tests\Unit\Services\ContactImport;

use App\Models\Proyecto;
use App\Services\ContactImport\ContactTextHomologationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTextHomologationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_homologate_resolves_comuna_and_proyecto(): void
    {
        Proyecto::factory()->create([
            'name' => 'Edificio Icon',
            'comuna' => 'Nunoa',
        ]);

        $result = app(ContactTextHomologationService::class)->homologate([
            'comuna' => 'ñuñoa',
            'proyecto' => 'edificio icon',
        ]);

        $this->assertSame('Nunoa', $result['fields']['comuna']);
        $this->assertSame('Edificio Icon', $result['fields']['proyecto']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_homologate_keeps_original_text_and_returns_warning_when_no_match(): void
    {
        $result = app(ContactTextHomologationService::class)->homologate([
            'comuna' => 'Comuna Fantasma',
            'proyecto' => 'Proyecto Fantasma',
        ]);

        $this->assertSame('Comuna Fantasma', $result['fields']['comuna']);
        $this->assertSame('Proyecto Fantasma', $result['fields']['proyecto']);
        $this->assertCount(2, $result['warnings']);
    }
}
