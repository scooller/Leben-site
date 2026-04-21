<?php

namespace Tests\Feature;

use App\Models\Asesor;
use App\Models\ContactSubmission;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\ShortLink;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AsesorProyectoActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogModelChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_authenticated_changes_for_plants(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($admin);

        $plant = Plant::factory()->create([
            'name' => 'Planta Inicial',
        ]);

        $plant->update([
            'name' => 'Planta Editada',
        ]);

        $createdActivity = Activity::query()
            ->where('subject_type', Plant::class)
            ->where('subject_id', $plant->getKey())
            ->where('event', 'created')
            ->first();

        $updatedActivity = Activity::query()
            ->where('subject_type', Plant::class)
            ->where('subject_id', $plant->getKey())
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($createdActivity);
        $this->assertSame($admin->getKey(), $createdActivity->causer_id);
        $this->assertSame(User::class, $createdActivity->causer_type);

        $this->assertNotNull($updatedActivity);
        $this->assertSame('Planta Inicial', $updatedActivity->properties['old']['name']);
        $this->assertSame('Planta Editada', $updatedActivity->properties['attributes']['name']);
    }

    public function test_it_logs_authenticated_changes_for_site_settings(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($admin);

        $settings = SiteSetting::current();

        Activity::query()->delete();

        $settings->update([
            'site_name' => 'Nuevo Nombre Leben',
        ]);

        $activity = Activity::query()
            ->where('subject_type', SiteSetting::class)
            ->where('subject_id', $settings->getKey())
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->getKey(), $activity->causer_id);
        $this->assertSame('Nuevo Nombre Leben', $activity->properties['attributes']['site_name']);
    }

    public function test_it_logs_attach_and_sync_changes_for_asesor_proyecto_relationship(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($admin);

        $asesor = Asesor::factory()->create();
        $proyectoA = Proyecto::factory()->create(['name' => 'Proyecto A']);
        $proyectoB = Proyecto::factory()->create(['name' => 'Proyecto B']);

        AsesorProyectoActivityLogger::logAttached($asesor, [$proyectoA, $proyectoB]);
        AsesorProyectoActivityLogger::logDetached($asesor, [$proyectoA]);
        AsesorProyectoActivityLogger::logSynced($proyectoA, [$asesor->getKey()], [$asesor->getKey() + 999]);

        $attachActivity = Activity::query()
            ->where('log_name', 'asesor_proyecto')
            ->where('subject_type', Asesor::class)
            ->where('subject_id', $asesor->getKey())
            ->where('description', 'Proyectos asignados a asesor')
            ->first();

        $detachActivity = Activity::query()
            ->where('log_name', 'asesor_proyecto')
            ->where('subject_type', Asesor::class)
            ->where('subject_id', $asesor->getKey())
            ->where('description', 'Proyectos removidos de asesor')
            ->first();

        $syncActivity = Activity::query()
            ->where('log_name', 'asesor_proyecto')
            ->where('subject_type', Proyecto::class)
            ->where('subject_id', $proyectoA->getKey())
            ->where('description', 'Asesores sincronizados en proyecto')
            ->first();

        $this->assertNotNull($attachActivity);
        $this->assertSame($admin->getKey(), $attachActivity->causer_id);
        $this->assertSame([$proyectoA->getKey(), $proyectoB->getKey()], $attachActivity->properties['proyecto_ids']);

        $this->assertNotNull($detachActivity);
        $this->assertSame([$proyectoA->getKey()], $detachActivity->properties['proyecto_ids']);

        $this->assertNotNull($syncActivity);
        $this->assertSame([$asesor->getKey()], $syncActivity->properties['attached_asesor_ids']);
        $this->assertSame([$asesor->getKey() + 999], $syncActivity->properties['detached_asesor_ids']);
    }

    public function test_it_logs_short_link_creation_and_contact_submission_updates(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($admin);

        Activity::query()->delete();

        $shortLink = ShortLink::factory()->create([
            'created_by' => $admin->getKey(),
        ]);

        $shortLinkCreatedActivity = Activity::query()
            ->where('subject_type', ShortLink::class)
            ->where('subject_id', $shortLink->getKey())
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($shortLinkCreatedActivity);
        $this->assertSame($admin->getKey(), $shortLinkCreatedActivity->causer_id);

        $contactSubmission = ContactSubmission::query()->create([
            'name' => 'Contacto Inicial',
            'email' => 'contacto@example.com',
            'phone' => '+56900000000',
            'rut' => '11.111.111-1',
            'fields' => ['comuna' => 'Santiago'],
            'recipient_email' => 'ventas@example.com',
            'submitted_at' => now(),
        ]);

        Activity::query()
            ->where('subject_type', ContactSubmission::class)
            ->where('subject_id', $contactSubmission->getKey())
            ->delete();

        $contactSubmission->update([
            'name' => 'Contacto Editado',
        ]);

        $contactUpdatedActivity = Activity::query()
            ->where('subject_type', ContactSubmission::class)
            ->where('subject_id', $contactSubmission->getKey())
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($contactUpdatedActivity);
        $this->assertSame($admin->getKey(), $contactUpdatedActivity->causer_id);
        $this->assertSame('Contacto Inicial', $contactUpdatedActivity->properties['old']['name']);
        $this->assertSame('Contacto Editado', $contactUpdatedActivity->properties['attributes']['name']);
    }
}
