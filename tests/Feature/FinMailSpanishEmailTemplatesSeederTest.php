<?php

namespace Tests\Feature;

use Database\Seeders\FinMailSpanishEmailTemplatesSeeder;
use FinityLabs\FinMail\Database\Seeders\EmailTemplateSeeder;
use FinityLabs\FinMail\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinMailSpanishEmailTemplatesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_spanish_locale_to_default_fin_mail_templates(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $this->seed(FinMailSpanishEmailTemplatesSeeder::class);

        $template = EmailTemplate::query()->where('key', 'user-verify-email')->first();

        $this->assertNotNull($template);
        $this->assertSame('Verificar correo', $template->getTranslation('name', 'es'));
        $this->assertSame('Verifica tu correo electronico', $template->getTranslation('subject', 'es'));
    }

    public function test_it_upserts_transactional_spanish_templates_idempotently(): void
    {
        $this->seed(FinMailSpanishEmailTemplatesSeeder::class);
        $this->seed(FinMailSpanishEmailTemplatesSeeder::class);

        $reservationTemplate = EmailTemplate::query()->where('key', 'unit-reserved')->first();
        $paymentTemplate = EmailTemplate::query()->where('key', 'payment-status-updated')->first();
        $manualReservationTemplate = EmailTemplate::query()->where('key', 'manual-reservation-created')->first();
        $manualProofAdminTemplate = EmailTemplate::query()->where('key', 'manual-payment-proof-submitted-admin')->first();

        $this->assertNotNull($reservationTemplate);
        $this->assertNotNull($paymentTemplate);
        $this->assertNotNull($manualReservationTemplate);
        $this->assertNotNull($manualProofAdminTemplate);
        $this->assertSame(1, EmailTemplate::query()->where('key', 'unit-reserved')->count());
        $this->assertSame(1, EmailTemplate::query()->where('key', 'payment-status-updated')->count());
        $this->assertSame(1, EmailTemplate::query()->where('key', 'manual-reservation-created')->count());
        $this->assertSame(1, EmailTemplate::query()->where('key', 'manual-payment-proof-submitted-admin')->count());
        $this->assertSame('Reserva de unidad confirmada', $reservationTemplate->getTranslation('name', 'es'));
        $this->assertStringContainsString('{{ reservation_amount', $reservationTemplate->getTranslation('body', 'es'));
        $this->assertStringContainsString('{{ reservation_currency', $reservationTemplate->getTranslation('body', 'es'));
        $this->assertSame('string', data_get($reservationTemplate->token_schema, 'reservation_amount'));
        $this->assertSame('string', data_get($reservationTemplate->token_schema, 'reservation_currency'));
        $this->assertSame('Actualizacion de estado de pago', $paymentTemplate->getTranslation('name', 'es'));
        $this->assertStringContainsString('{{ payment.amount', $paymentTemplate->getTranslation('body', 'es'));
        $this->assertStringContainsString('{{ payment.currency', $paymentTemplate->getTranslation('body', 'es'));
        $this->assertSame('Reserva manual creada', $manualReservationTemplate->getTranslation('name', 'es'));
        $this->assertSame('Comprobante manual recibido (admin)', $manualProofAdminTemplate->getTranslation('name', 'es'));
    }
}
