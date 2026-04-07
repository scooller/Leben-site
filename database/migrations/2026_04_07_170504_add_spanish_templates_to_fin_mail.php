<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $this->addSpanishLocaleToDefaultTemplates();
        $this->upsertTransactionalTemplates();
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')
            ->whereIn('key', ['unit-reserved', 'payment-status-updated'])
            ->delete();
    }

    private function addSpanishLocaleToDefaultTemplates(): void
    {
        $defaults = [
            'user-verify-email' => [
                'name' => 'Verificar correo',
                'subject' => 'Verifica tu correo electronico',
                'preheader' => 'Confirma tu cuenta para continuar.',
                'body' => '<p>Hola {{ user.name | "Usuario" }},</p><p>Para activar tu cuenta, confirma tu correo con el siguiente enlace:</p><p><a href="{{ url }}">Verificar correo</a></p>',
            ],
            'user-password-reset' => [
                'name' => 'Restablecer contrasena',
                'subject' => 'Restablece tu contrasena',
                'preheader' => 'Se solicito un cambio de contrasena para tu cuenta.',
                'body' => '<p>Hola {{ user.name | "Usuario" }},</p><p>Recibimos una solicitud para restablecer tu contrasena.</p><p><a href="{{ url }}">Restablecer contrasena</a></p>',
            ],
            'user-welcome' => [
                'name' => 'Bienvenida',
                'subject' => 'Bienvenido a {{ config.app.name }}',
                'preheader' => 'Tu cuenta ya esta lista.',
                'body' => '<p>Hola {{ user.name | "Usuario" }},</p><p>Te damos la bienvenida a {{ config.app.name }}.</p>',
            ],
            'user-password-changed' => [
                'name' => 'Contrasena actualizada',
                'subject' => 'Tu contrasena fue actualizada',
                'preheader' => 'Si no realizaste este cambio, contactanos de inmediato.',
                'body' => '<p>Hola {{ user.name | "Usuario" }},</p><p>Tu contrasena fue actualizada correctamente.</p>',
            ],
            'general-notification' => [
                'name' => 'Notificacion general',
                'subject' => 'Nueva notificacion de {{ config.app.name }}',
                'preheader' => 'Tienes una nueva actualizacion.',
                'body' => '<p>Hola {{ user.name | "Usuario" }},</p><p>Tenemos una actualizacion para ti.</p>',
            ],
        ];

        foreach ($defaults as $key => $content) {
            $template = DB::table('email_templates')->where('key', $key)->first();

            if (! $template) {
                continue;
            }

            $name = $this->mergeLocale($template->name, 'es', $content['name']);
            $subject = $this->mergeLocale($template->subject, 'es', $content['subject']);
            $preheader = $this->mergeLocale($template->preheader, 'es', $content['preheader']);
            $body = $this->mergeLocale($template->body, 'es', $content['body']);

            DB::table('email_templates')
                ->where('id', $template->id)
                ->update([
                    'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
                    'subject' => json_encode($subject, JSON_UNESCAPED_UNICODE),
                    'preheader' => json_encode($preheader, JSON_UNESCAPED_UNICODE),
                    'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
                    'updated_at' => Carbon::now(),
                ]);
        }
    }

    private function upsertTransactionalTemplates(): void
    {
        $now = Carbon::now();

        $templates = [
            [
                'key' => 'unit-reserved',
                'name' => [
                    'es' => 'Reserva de unidad confirmada',
                    'en' => 'Unit reservation confirmed',
                ],
                'subject' => [
                    'es' => 'Tu reserva de unidad fue creada',
                    'en' => 'Your unit reservation was created',
                ],
                'preheader' => [
                    'es' => 'Tu reserva esta activa por tiempo limitado.',
                    'en' => 'Your reservation is active for a limited time.',
                ],
                'body' => [
                    'es' => '<p>Hola {{ user.name | "Cliente" }},</p><p>Tu reserva para la unidad {{ plant.name }} del proyecto {{ project.name | "-" }} fue creada correctamente.</p><p>Vigencia: {{ reservation.expires_at }}</p>',
                    'en' => '<p>Hello {{ user.name | "Customer" }},</p><p>Your reservation for unit {{ plant.name }} in project {{ project.name | "-" }} was created successfully.</p><p>Valid until: {{ reservation.expires_at }}</p>',
                ],
                'category' => 'transactional',
                'tags' => ['reservation', 'plant'],
                'token_schema' => [
                    'user' => ['name', 'email'],
                    'plant' => ['name'],
                    'project' => ['name'],
                    'reservation' => ['expires_at', 'session_token'],
                ],
            ],
            [
                'key' => 'payment-status-updated',
                'name' => [
                    'es' => 'Actualizacion de estado de pago',
                    'en' => 'Payment status update',
                ],
                'subject' => [
                    'es' => 'Tu pago cambio a {{ current_status }}',
                    'en' => 'Your payment changed to {{ current_status }}',
                ],
                'preheader' => [
                    'es' => 'Revisa el nuevo estado de tu pago.',
                    'en' => 'Check your new payment status.',
                ],
                'body' => [
                    'es' => '<p>Hola {{ user.name | "Cliente" }},</p><p>Tu pago para la unidad {{ plant.name | "-" }} cambio de estado.</p><p>Estado anterior: {{ previous_status }}</p><p>Estado actual: {{ current_status }}</p>',
                    'en' => '<p>Hello {{ user.name | "Customer" }},</p><p>Your payment for unit {{ plant.name | "-" }} changed status.</p><p>Previous status: {{ previous_status }}</p><p>Current status: {{ current_status }}</p>',
                ],
                'category' => 'transactional',
                'tags' => ['payment', 'status'],
                'token_schema' => [
                    'user' => ['name', 'email'],
                    'plant' => ['name'],
                    'project' => ['name'],
                    'payment' => ['gateway_tx_id', 'amount', 'currency'],
                    'previous_status' => 'string',
                    'current_status' => 'string',
                ],
            ],
        ];

        foreach ($templates as $template) {
            DB::table('email_templates')->updateOrInsert(
                ['key' => $template['key']],
                [
                    'name' => json_encode($template['name'], JSON_UNESCAPED_UNICODE),
                    'category' => $template['category'],
                    'tags' => json_encode($template['tags'], JSON_UNESCAPED_UNICODE),
                    'subject' => json_encode($template['subject'], JSON_UNESCAPED_UNICODE),
                    'preheader' => json_encode($template['preheader'], JSON_UNESCAPED_UNICODE),
                    'body' => json_encode($template['body'], JSON_UNESCAPED_UNICODE),
                    'token_schema' => json_encode($template['token_schema'], JSON_UNESCAPED_UNICODE),
                    'is_active' => true,
                    'is_locked' => false,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeLocale(mixed $jsonValue, string $locale, string $value): array
    {
        $decoded = [];

        if (is_string($jsonValue)) {
            $parsed = json_decode($jsonValue, true);
            $decoded = is_array($parsed) ? $parsed : [];
        } elseif (is_array($jsonValue)) {
            $decoded = $jsonValue;
        }

        if (! array_key_exists($locale, $decoded) || blank($decoded[$locale])) {
            $decoded[$locale] = $value;
        }

        return $decoded;
    }
};
