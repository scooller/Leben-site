<?php

namespace Database\Seeders;

use FinityLabs\FinMail\Database\Seeders\EmailTemplateSeeder;
use FinityLabs\FinMail\Models\EmailTemplate;
use FinityLabs\FinMail\Settings\AuthEmailSettings;
use Illuminate\Database\Seeder;

class FinMailPasswordResetTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(EmailTemplateSeeder::class);

        $template = EmailTemplate::query()->firstOrNew([
            'key' => 'user-password-reset',
        ]);

        $template->category = 'system';
        $template->is_active = true;
        $template->is_locked = true;
        $template->name = $this->mergeLocale($template->getTranslations('name'), 'es', 'Restablecer contrasena');
        $template->subject = $this->mergeLocale($template->getTranslations('subject'), 'es', 'Restablece tu contrasena');
        $template->preheader = $this->mergeLocale($template->getTranslations('preheader'), 'es', 'Se solicito un cambio de contrasena para tu cuenta.');
        $template->body = $this->mergeLocale(
            $template->getTranslations('body'),
            'es',
            '<p>Hola {{ user.name | "Usuario" }},</p><p>Recibimos una solicitud para restablecer tu contrasena.</p><p><a href="{{ url }}">Restablecer contrasena</a></p><p>Este enlace expirara en 60 minutos.</p>'
        );
        $template->token_schema = [
            [
                'token' => 'user.name',
                'description' => 'User name',
                'example' => 'John Doe',
            ],
            [
                'token' => 'url',
                'description' => 'Password reset URL',
                'example' => 'https://example.com/reset/...',
            ],
            [
                'token' => 'config.app.name',
                'description' => 'Application name',
                'example' => 'MyApp',
            ],
        ];
        $template->save();

        $authEmailSettings = app(AuthEmailSettings::class);
        $authEmailSettings->override_password_reset = true;
        $authEmailSettings->save();
    }

    /**
     * @param  array<string, string>  $translations
     * @return array<string, string>
     */
    private function mergeLocale(array $translations, string $locale, string $value): array
    {
        $translations[$locale] = $value;

        return $translations;
    }
}
