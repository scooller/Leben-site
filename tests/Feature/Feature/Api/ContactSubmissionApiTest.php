<?php

namespace Tests\Feature\Feature\Api;

use App\Filament\Resources\ContactSubmissions\ContactSubmissions\ContactSubmissionResource;
use App\Jobs\CreateSalesforceCaseJob;
use App\Models\ContactSubmission;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\FinMailSpanishEmailTemplatesSeeder;
use FinityLabs\FinMail\Mail\TemplateMail;
use FinityLabs\FinMail\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
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

    public function test_it_requires_commune_and_project_fields(): void
    {
        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Sin comuna ni proyecto',
                'email' => 'sin-comuna@example.com',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.comuna', 'fields.proyecto']);
    }

    public function test_it_infers_utm_site_from_referer_when_frontend_does_not_send_it(): void
    {
        SiteSetting::current()->update([
            'contact_notification_email' => null,
            'contact_email' => null,
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ],
        ]);

        $response = $this
            ->withHeaders([
                'Referer' => 'https://sale.ileben.cl/landing/campana',
            ])
            ->postJson('/api/v1/contact-submissions', [
                'fields' => [
                    'name' => 'Sin UTM Site',
                    'email' => 'sin-utmsite@example.com',
                    'utm_source' => 'instagram',
                ],
            ]);

        $response->assertCreated();

        $submission = ContactSubmission::query()->latest('id')->first();

        $this->assertNotNull($submission);
        $this->assertSame('sale.ileben.cl', $submission->fields['utm_site'] ?? null);
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

    public function test_it_requires_turnstile_token_when_turnstile_is_enabled(): void
    {
        config()->set('services.turnstile.secret_key', 'test-secret');

        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Juan Perez',
                'email' => 'juan@example.com',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);
    }

    public function test_it_rejects_contact_submission_when_turnstile_validation_fails(): void
    {
        config()->set('services.turnstile.secret_key', 'test-secret');

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => false,
            ], 200),
        ]);

        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Juan Perez',
                'email' => 'juan@example.com',
            ],
            'turnstile_token' => 'invalid-token',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);
    }

    public function test_it_accepts_contact_submission_when_turnstile_validation_succeeds(): void
    {
        Mail::fake();
        $this->seed(FinMailSpanishEmailTemplatesSeeder::class);

        config()->set('services.turnstile.secret_key', 'test-secret');

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
            ], 200),
        ]);

        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ],
            'contact_notification_email' => 'leads@ileben.cl',
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Juan Perez',
                'email' => 'juan@example.com',
            ],
            'turnstile_token' => 'valid-token',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Tu mensaje fue enviado correctamente.');

        $this->assertDatabaseHas('contact_submissions', [
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
        ]);
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
        $this->assertSame('telefono', collect($template->token_schema)->firstWhere('token', 'telefono')['token'] ?? null);
        $this->assertSame('comuna', collect($template->token_schema)->firstWhere('token', 'comuna')['token'] ?? null);
        $this->assertSame('proyecto', collect($template->token_schema)->firstWhere('token', 'proyecto')['token'] ?? null);
    }

    public function test_it_accepts_commune_and_project_aliases_in_contact_submission_fields(): void
    {
        Mail::fake();
        $this->seed(FinMailSpanishEmailTemplatesSeeder::class);

        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['key' => 'message', 'label' => 'Mensaje', 'type' => 'textarea', 'required' => true],
            ],
            'contact_notification_email' => 'leads@ileben.cl',
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Juan Perez',
                'email' => 'juan@example.com',
                'message' => 'Quiero información del proyecto.',
                'commune' => 'Providencia',
                'project_name' => 'Edificio Andes',
            ],
        ]);

        $response->assertCreated();

        $submission = ContactSubmission::query()->latest('id')->first();

        $this->assertNotNull($submission);
        $this->assertSame('Providencia', $submission->fields['commune']);
        $this->assertSame('Edificio Andes', $submission->fields['project_name']);

        Mail::assertSent(TemplateMail::class);
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

    public function test_it_ignores_required_conditional_field_when_selected_project_does_not_match(): void
    {
        Proyecto::factory()->create([
            'name' => 'Proyecto Icon',
            'comuna' => 'Providencia',
            'tipo' => ['icon'],
            'is_active' => true,
        ]);

        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                [
                    'key' => 'rango',
                    'label' => 'Rango de renta',
                    'type' => 'select',
                    'required' => true,
                    'projects' => ['Proyecto Best'],
                    'options' => [
                        ['value' => '1', 'label' => 'Menor a $1.000.000'],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'proyecto' => 'Proyecto Icon',
                'comuna' => 'Providencia',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.name'])
            ->assertJsonMissingValidationErrors(['fields.rango']);
    }

    public function test_it_ignores_required_conditional_field_when_selected_range_project_does_not_match(): void
    {
        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                [
                    'key' => 'rango',
                    'label' => 'Rango de renta',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'best_1', 'label' => 'Best 1', 'projects' => ['Proyecto Best']],
                        ['value' => 'icon_1', 'label' => 'Icon 1', 'projects' => ['Proyecto Icon']],
                    ],
                ],
                [
                    'key' => 'codeudor',
                    'label' => 'Codeudor',
                    'type' => 'select',
                    'required' => true,
                    'projects' => ['Proyecto Best'],
                    'options' => [
                        ['value' => 'si', 'label' => 'Si'],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'rango' => 'icon_1',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.name'])
            ->assertJsonMissingValidationErrors(['fields.codeudor', 'fields.rango']);
    }

    public function test_it_requires_matching_conditional_field_when_selected_range_project_matches(): void
    {
        Proyecto::factory()->create([
            'name' => 'Proyecto Best',
            'comuna' => 'Santiago',
            'is_active' => true,
        ]);

        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                [
                    'key' => 'rango',
                    'label' => 'Rango de renta',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'best_1', 'label' => 'Best 1', 'projects' => ['Proyecto Best']],
                        ['value' => 'icon_1', 'label' => 'Icon 1', 'projects' => ['Proyecto Icon']],
                    ],
                ],
                [
                    'key' => 'codeudor',
                    'label' => 'Codeudor',
                    'type' => 'select',
                    'required' => true,
                    'projects' => ['Proyecto Best'],
                    'options' => [
                        ['value' => 'si', 'label' => 'Si'],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Juan',
                'rango' => 'best_1',
                'proyecto' => 'Proyecto Best',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.codeudor'])
            ->assertJsonMissingValidationErrors(['fields.rango']);
    }

    public function test_it_uses_selected_range_when_same_key_is_reused(): void
    {
        Proyecto::factory()->create([
            'name' => 'Proyecto Best',
            'comuna' => 'Santiago',
            'tipo' => ['best'],
            'is_active' => true,
        ]);

        SiteSetting::current()->update([
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                [
                    'key' => 'rango',
                    'label' => 'Rango de renta',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'best_1', 'label' => 'Best 1', 'projects' => ['Proyecto Best']],
                        ['value' => 'icon_1', 'label' => 'Icon 1', 'projects' => ['Proyecto Icon']],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'proyecto' => 'Proyecto Best',
                'comuna' => 'Santiago',
                'rango' => 'icon_1',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.name'])
            ->assertJsonMissingValidationErrors(['fields.rango']);
    }

    public function test_it_dispatches_salesforce_case_job_when_enabled(): void
    {
        Bus::fake();

        config()->set('services.salesforce.lead_enabled', true);

        SiteSetting::current()->update([
            'contact_email' => null,
            'contact_notification_email' => null,
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['key' => 'message', 'label' => 'Mensaje', 'type' => 'textarea', 'required' => true],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Alejandro',
                'email' => 'alejandro@example.com',
                'message' => 'Quiero información.',
                'utm_campaign' => 'BlackFriday',
            ],
        ]);

        $response->assertCreated();

        Bus::assertDispatchedSync(CreateSalesforceCaseJob::class);
    }

    public function test_it_does_not_dispatch_salesforce_case_job_when_disabled(): void
    {
        Bus::fake();

        config()->set('services.salesforce.lead_enabled', false);
        config()->set('services.salesforce.case_enabled', false);

        SiteSetting::current()->update([
            'contact_email' => null,
            'contact_notification_email' => null,
            'contact_form_fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['key' => 'message', 'label' => 'Mensaje', 'type' => 'textarea', 'required' => true],
            ],
        ]);

        $response = $this->postJson('/api/v1/contact-submissions', [
            'fields' => [
                'name' => 'Alejandro',
                'email' => 'alejandro@example.com',
                'message' => 'Quiero información.',
            ],
        ]);

        $response->assertCreated();

        Bus::assertNotDispatched(CreateSalesforceCaseJob::class);
    }
}
