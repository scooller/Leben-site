<?php

namespace Tests\Feature;

use Database\Seeders\FinMailPasswordResetTemplateSeeder;
use FinityLabs\FinMail\Models\EmailTemplate;
use FinityLabs\FinMail\Settings\AuthEmailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinMailPasswordResetTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_upserts_password_reset_template_and_enables_override(): void
    {
        $this->seed(FinMailPasswordResetTemplateSeeder::class);
        $this->seed(FinMailPasswordResetTemplateSeeder::class);

        $template = EmailTemplate::query()->where('key', 'user-password-reset')->first();

        $this->assertNotNull($template);
        $this->assertSame(1, EmailTemplate::query()->where('key', 'user-password-reset')->count());
        $this->assertTrue((bool) $template->is_active);
        $this->assertTrue((bool) $template->is_locked);
        $this->assertSame('Restablece tu contrasena', $template->getTranslation('subject', 'es'));
        $this->assertStringContainsString('{{ url }}', $template->getTranslation('body', 'es'));
        $this->assertNotNull(collect($template->token_schema)->firstWhere('token', 'url'));

        $authEmailSettings = app(AuthEmailSettings::class);

        $this->assertTrue($authEmailSettings->override_password_reset);
    }
}
