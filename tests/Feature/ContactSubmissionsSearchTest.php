<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactSubmissions\ContactSubmissions\Pages\ListContactSubmissions;
use App\Filament\Resources\ContactSubmissions\ContactSubmissions\Tables\ContactSubmissionsTable;
use App\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactSubmissionsSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_search_query_matches_by_exact_email(): void
    {
        $target = ContactSubmission::query()->create([
            'name' => 'Camila Silva',
            'email' => 'camila.silva@example.com',
            'rut' => '12.345.678-5',
            'fields' => ['source' => 'web'],
            'submitted_at' => now(),
        ]);

        $other = ContactSubmission::query()->create([
            'name' => 'Pedro Soto',
            'email' => 'pedro.soto@example.com',
            'rut' => '19.876.543-2',
            'fields' => ['source' => 'web'],
            'submitted_at' => now(),
        ]);

        $results = ContactSubmissionsTable::applySearchQuery(ContactSubmission::query(), 'camila.silva@example.com')->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($target));
    }

    public function test_apply_search_query_matches_by_partial_email(): void
    {
        $target = ContactSubmission::query()->create([
            'name' => 'Gonzalo Morales',
            'email' => 'gonzalo.morales@corporacion.cl',
            'rut' => '15.678.901-2',
            'fields' => ['source' => 'web'],
            'submitted_at' => now(),
        ]);

        $other = ContactSubmission::query()->create([
            'name' => 'Ana Torres',
            'email' => 'ana.torres@gmail.com',
            'rut' => '16.789.012-3',
            'fields' => ['source' => 'web'],
            'submitted_at' => now(),
        ]);

        $results = ContactSubmissionsTable::applySearchQuery(ContactSubmission::query(), 'gonzalo.morales')->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($target));
    }

    public function test_apply_search_query_matches_rut_with_dots_and_dash(): void
    {
        $target = ContactSubmission::query()->create([
            'name' => 'Claudio Bravo',
            'email' => 'claudio@example.com',
            'rut' => '12.345.678-5',
            'fields' => ['source' => 'web'],
            'submitted_at' => now(),
        ]);

        $results = ContactSubmissionsTable::applySearchQuery(ContactSubmission::query(), '12.345.678-5')->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($target));
    }

    public function test_apply_search_query_matches_rut_without_dots(): void
    {
        // Guardado con puntos en BD, buscado sin puntos pero con guion
        $target = ContactSubmission::query()->create([
            'name' => 'Claudio Bravo',
            'email' => 'claudio@example.com',
            'rut' => '12.345.678-5',
            'fields' => ['source' => 'web'],
            'submitted_at' => now(),
        ]);

        $results = ContactSubmissionsTable::applySearchQuery(ContactSubmission::query(), '12345678-5')->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($target));
    }

    public function test_apply_search_query_matches_rut_without_dots_and_without_dash(): void
    {
        // Guardado con puntos y guion en BD, buscado solo números
        $target = ContactSubmission::query()->create([
            'name' => 'Claudio Bravo',
            'email' => 'claudio@example.com',
            'rut' => '12.345.678-5',
            'fields' => ['source' => 'web'],
            'submitted_at' => now(),
        ]);

        $results = ContactSubmissionsTable::applySearchQuery(ContactSubmission::query(), '123456785')->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($target));
    }

    public function test_apply_search_query_matches_rut_stored_clean_when_searched_with_dots(): void
    {
        // Guardado limpio en BD (123456785), buscado con formato (12.345.678-5)
        $target = ContactSubmission::query()->create([
            'name' => 'Ignacio Valenzuela',
            'email' => 'ignacio@example.com',
            'rut' => '123456785',
            'fields' => ['source' => 'web'],
            'submitted_at' => now(),
        ]);

        $results = ContactSubmissionsTable::applySearchQuery(ContactSubmission::query(), '12.345.678-5')->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($target));
    }

    public function test_apply_search_query_matches_email_and_rut_inside_json_fields(): void
    {
        $target = ContactSubmission::query()->create([
            'name' => null,
            'email' => null,
            'rut' => null,
            'fields' => [
                'rut' => '17.654.321-K',
                'email' => 'contacto.json@empresa.com',
            ],
            'submitted_at' => now(),
        ]);

        $resultsEmail = ContactSubmissionsTable::applySearchQuery(ContactSubmission::query(), 'contacto.json@empresa.com')->get();
        $this->assertCount(1, $resultsEmail);
        $this->assertTrue($resultsEmail->first()->is($target));

        $resultsRut = ContactSubmissionsTable::applySearchQuery(ContactSubmission::query(), '17654321k')->get();
        $this->assertCount(1, $resultsRut);
        $this->assertTrue($resultsRut->first()->is($target));
    }

    public function test_filament_table_searches_by_email_and_rut(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);

        $submissionA = ContactSubmission::query()->create([
            'name' => 'Francisca Diaz',
            'email' => 'francisca@example.com',
            'rut' => '18.234.567-8',
            'fields' => ['source' => 'web'],
            'submitted_at' => now(),
        ]);

        $submissionB = ContactSubmission::query()->create([
            'name' => 'Manuel Rojas',
            'email' => 'manuel@example.com',
            'rut' => '19.345.678-9',
            'fields' => ['source' => 'web'],
            'submitted_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ListContactSubmissions::class)
            ->searchTable('francisca@example.com')
            ->assertCanSeeTableRecords([$submissionA])
            ->assertCanNotSeeTableRecords([$submissionB]);

        Livewire::actingAs($admin)
            ->test(ListContactSubmissions::class)
            ->searchTable('19345678-9')
            ->assertCanSeeTableRecords([$submissionB])
            ->assertCanNotSeeTableRecords([$submissionA]);
    }
}
