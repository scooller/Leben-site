<?php

namespace Tests\Feature\Feature\Api;

use App\Filament\Resources\ContactSubmissions\ContactSubmissions\ContactSubmissionResource;
use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\FinMailSpanishEmailTemplatesSeeder;
use FinityLabs\FinMail\Mail\TemplateMail;
use FinityLabs\FinMail\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactSubmissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_contact_submission_and_sends_email_to_configured_recipient(): void
    {
        Mail::fake();
        $this->seed(FinMailSpanishEmailTemplatesSeeder::class);
        config()->set('mail.template_cc.contact-submission-received-admin', ['copias@ileben.cl']);

        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'rut', 'label' => 'RUT', 'type' => 'rut', 'required' => false],
                [
                    'key' => 'reason',
                    'label' => 'Motivo',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'cotizacion', 'label' => 'Cotización'],
                        ['value' => 'visita', 'label' => 'Agendar visita'],
                    ],
                ],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['key' => 'message', 'label' => 'Mensaje', 'type' => 'textarea', 'required' => true],
            ],
            'contact_notification_email' => 'leads@ileben.cl',
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Juan Perez',
                'rut' => '12.345.678-5',
                'reason' => 'cotizacion',
                'email' => 'juan@example.com',
                'message' => 'Quiero información del proyecto.',
                'utm_source' => 'facebook',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'summer-launch',
                'utm_site' => 'capitanes.ileben.cl',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Tu mensaje fue enviado correctamente.');

        $this->assertDatabaseHas('contact_submissions', [
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
            'rut' => '12.345.678-5',
            'recipient_email' => 'leads@ileben.cl',
        ]);

        $submission = ContactSubmission::query()->first();

        $this->assertNotNull($submission);
        $this->assertSame('cotizacion', $submission->fields['reason']);
        $this->assertSame('Quiero información del proyecto.', $submission->fields['message']);
        $this->assertSame('facebook', $submission->fields['utm_source']);
        $this->assertSame('summer-launch', $submission->fields['utm_campaign']);

        Mail::assertSent(TemplateMail::class, function (TemplateMail $mail) {
            return $mail->hasTo('leads@ileben.cl')
                && $mail->hasCc('copias@ileben.cl');
        });
    }

    public function test_it_validates_required_dynamic_fields(): void
    {
        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Sin Email',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.email']);
    }

    public function test_it_validates_rut_and_select_dynamic_fields(): void
    {
        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'rut', 'label' => 'RUT', 'type' => 'rut', 'required' => true],
                [
                    'key' => 'reason',
                    'label' => 'Motivo',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'cotizacion', 'label' => 'Cotización'],
                        ['value' => 'visita', 'label' => 'Agendar visita'],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'rut' => '12.345.678-9',
                'reason' => 'otro',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.rut', 'fields.reason']);
    }

    public function test_finmail_seeder_creates_contact_template_with_requested_fields(): void
    {
        $this->seed(FinMailSpanishEmailTemplatesSeeder::class);

        $template = EmailTemplate::query()
            ->where('key', 'contact-submission-received-admin')
            ->first();

        $this->assertNotNull($template);
        $this->assertSame('Contacto recibido (admin)', $template->getTranslation('name', 'es'));
        $this->assertStringContainsString('Comuna', $template->getTranslation('body', 'es'));
        $this->assertStringContainsString('Proyecto', $template->getTranslation('body', 'es'));
        $this->assertStringContainsString('Medio de llegada', $template->getTranslation('body', 'es'));
        $this->assertStringContainsString('codeudor', strtolower($template->getTranslation('body', 'es')));
    }

    public function test_admin_can_view_contact_submission_list_with_dynamic_columns(): void
    {
        $admin = User::factory()->create([
            'user_type' => 'admin',
        ]);

        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre completo', 'type' => 'text', 'required' => true],
                [
                    'key' => 'reason',
                    'label' => 'Motivo',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'cotizacion', 'label' => 'Cotización'],
                        ['value' => 'visita', 'label' => 'Agendar visita'],
                    ],
                ],
            ],
        ]);

        ContactSubmission::query()->create([
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'fields' => [
                'name' => 'Juan Pérez',
                'reason' => 'cotizacion',
                'comuna' => 'Las Condes',
                'proyecto' => 'Edificio Andes',
            ],
            'recipient_email' => 'leads@ileben.cl',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(ContactSubmissionResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Nombre completo')
            ->assertSee('Motivo')
            ->assertSee('Cotización')
            ->assertSee('Comuna')
            ->assertSee('Las Condes')
            ->assertSee('Proyecto')
            ->assertSee('Edificio Andes');
    }

    public function test_admin_can_view_contact_submission_detail_with_dynamic_fields(): void
    {
        $admin = User::factory()->create([
            'user_type' => 'admin',
        ]);

        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre completo', 'type' => 'text', 'required' => true],
                [
                    'key' => 'reason',
                    'label' => 'Motivo',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'cotizacion', 'label' => 'Cotización'],
                        ['value' => 'visita', 'label' => 'Agendar visita'],
                    ],
                ],
                ['key' => 'message', 'label' => 'Mensaje', 'type' => 'textarea', 'required' => true],
            ],
        ]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'phone' => '+56 9 1234 5678',
            'rut' => '12.345.678-5',
            'fields' => [
                'name' => 'Juan Pérez',
                'reason' => 'cotizacion',
                'comuna' => 'Providencia',
                'proyecto' => 'Parque Central',
                'message' => 'Necesito más información.',
            ],
            'recipient_email' => 'leads@ileben.cl',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(ContactSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertSee('Resumen del envío')
            ->assertSee('Campos enviados')
            ->assertSee('Nombre completo')
            ->assertSee('Cotización')
            ->assertSee('Comuna')
            ->assertSee('Providencia')
            ->assertSee('Proyecto')
            ->assertSee('Parque Central')
            ->assertDontSee('Teléfono');
    }
}
