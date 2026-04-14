<?php

namespace Database\Seeders;

use FinityLabs\FinMail\Database\Seeders\EmailTemplateSeeder;
use FinityLabs\FinMail\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class FinMailSpanishEmailTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(EmailTemplateSeeder::class);

        $this->seedSpanishForSystemTemplates();
        $this->upsertTransactionalTemplates();
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function getSystemTranslations(): array
    {
        return [
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
    }

    protected function seedSpanishForSystemTemplates(): void
    {
        $translations = $this->getSystemTranslations();

        foreach ($translations as $key => $content) {
            $template = EmailTemplate::query()->where('key', $key)->first();

            if (! $template) {
                continue;
            }

            $template->name = $this->mergeLocale($template->getTranslations('name'), 'es', $content['name']);
            $template->subject = $this->mergeLocale($template->getTranslations('subject'), 'es', $content['subject']);
            $template->preheader = $this->mergeLocale($template->getTranslations('preheader'), 'es', $content['preheader']);
            $template->body = $this->mergeLocale($template->getTranslations('body'), 'es', $content['body']);
            $template->save();
        }
    }

    protected function upsertTransactionalTemplates(): void
    {
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
                    'es' => '<p>Hola {{ user.name | "Cliente" }},</p><p>Tu reserva para la unidad {{ plant.name }} del proyecto {{ project.name | "-" }} fue creada correctamente.</p><p><strong>Monto de reserva:</strong> {{ reservation_amount | "-" }} {{ reservation_currency | "CLP" }}</p><p>Vigencia: {{ reservation.expires_at }}</p>',
                    'en' => '<p>Hello {{ user.name | "Customer" }},</p><p>Your reservation for unit {{ plant.name }} in project {{ project.name | "-" }} was created successfully.</p><p><strong>Reservation amount:</strong> {{ reservation_amount | "-" }} {{ reservation_currency | "CLP" }}</p><p>Valid until: {{ reservation.expires_at }}</p>',
                ],
                'category' => 'transactional',
                'tags' => ['reservation', 'plant'],
                'token_schema' => [
                    'user' => ['name', 'email'],
                    'plant' => ['name'],
                    'project' => ['name'],
                    'reservation' => ['expires_at', 'session_token'],
                    'reservation_amount' => 'string',
                    'reservation_currency' => 'string',
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
                    'es' => '<p>Hola {{ user.name | "Cliente" }},</p><p>Tu pago para la unidad {{ plant.name | "-" }} cambio de estado.</p><p><strong>Monto de reserva:</strong> {{ payment.amount | "-" }} {{ payment.currency | "CLP" }}</p><p>Estado anterior: {{ previous_status }}</p><p>Estado actual: {{ current_status }}</p>',
                    'en' => '<p>Hello {{ user.name | "Customer" }},</p><p>Your payment for unit {{ plant.name | "-" }} changed status.</p><p><strong>Reservation amount:</strong> {{ payment.amount | "-" }} {{ payment.currency | "CLP" }}</p><p>Previous status: {{ previous_status }}</p><p>Current status: {{ current_status }}</p>',
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
            [
                'key' => 'manual-reservation-created',
                'name' => [
                    'es' => 'Reserva manual creada',
                    'en' => 'Manual reservation created',
                ],
                'subject' => [
                    'es' => 'Tu reserva manual fue creada (Ref: {{ payment.gateway_tx_id | "-" }})',
                    'en' => 'Your manual reservation was created (Ref: {{ payment.gateway_tx_id | "-" }})',
                ],
                'preheader' => [
                    'es' => 'Comparte tu comprobante antes del vencimiento para validar el pago.',
                    'en' => 'Upload your payment proof before expiration to validate your payment.',
                ],
                'body' => [
                    'es' => '<p>Hola {{ user.name | "Cliente" }},</p><p>Tu reserva manual para la unidad {{ plant.name | "-" }} del proyecto {{ project.name | "-" }} fue creada.</p><p><strong>Referencia unica:</strong> {{ payment.gateway_tx_id | "-" }}</p><p><strong>Monto:</strong> {{ payment.amount | "-" }} {{ payment.currency | "CLP" }}</p><p><strong>Vigencia:</strong> {{ reservation.expires_at | "-" }}</p>',
                    'en' => '<p>Hello {{ user.name | "Customer" }},</p><p>Your manual reservation for unit {{ plant.name | "-" }} in project {{ project.name | "-" }} was created.</p><p><strong>Unique reference:</strong> {{ payment.gateway_tx_id | "-" }}</p><p><strong>Amount:</strong> {{ payment.amount | "-" }} {{ payment.currency | "CLP" }}</p><p><strong>Valid until:</strong> {{ reservation.expires_at | "-" }}</p>',
                ],
                'category' => 'transactional',
                'tags' => ['reservation', 'manual', 'payment'],
                'token_schema' => [
                    'user' => ['name', 'email'],
                    'plant' => ['name'],
                    'project' => ['name'],
                    'reservation' => ['expires_at', 'session_token'],
                    'payment' => ['gateway_tx_id', 'amount', 'currency'],
                ],
            ],
            [
                'key' => 'manual-payment-proof-submitted-admin',
                'name' => [
                    'es' => 'Comprobante manual recibido (admin)',
                    'en' => 'Manual proof received (admin)',
                ],
                'subject' => [
                    'es' => 'Pago pendiente de aprobacion: {{ payment.gateway_tx_id | "-" }}',
                    'en' => 'Payment pending approval: {{ payment.gateway_tx_id | "-" }}',
                ],
                'preheader' => [
                    'es' => 'Se recibio un comprobante manual y requiere revision.',
                    'en' => 'A manual payment proof was received and requires review.',
                ],
                'body' => [
                    'es' => '<p>Se recibio un comprobante para un pago manual.</p><p><strong>Referencia unica:</strong> {{ payment.gateway_tx_id | "-" }}</p><p><strong>Monto:</strong> {{ payment.amount | "-" }} {{ payment.currency | "CLP" }}</p><p><strong>Cliente:</strong> {{ user.name | "-" }} ({{ user.email | "-" }})</p><p><strong>Unidad:</strong> {{ plant.name | "-" }}</p><p><strong>Proyecto:</strong> {{ project.name | "-" }}</p>',
                    'en' => '<p>A payment proof was received for a manual payment.</p><p><strong>Unique reference:</strong> {{ payment.gateway_tx_id | "-" }}</p><p><strong>Amount:</strong> {{ payment.amount | "-" }} {{ payment.currency | "CLP" }}</p><p><strong>Customer:</strong> {{ user.name | "-" }} ({{ user.email | "-" }})</p><p><strong>Unit:</strong> {{ plant.name | "-" }}</p><p><strong>Project:</strong> {{ project.name | "-" }}</p>',
                ],
                'category' => 'transactional',
                'tags' => ['manual', 'payment', 'admin'],
                'token_schema' => [
                    'user' => ['name', 'email'],
                    'plant' => ['name'],
                    'project' => ['name'],
                    'payment' => ['gateway_tx_id', 'amount', 'currency', 'status'],
                ],
            ],
            [
                'key' => 'contact-submission-received-admin',
                'name' => [
                    'es' => 'Contacto recibido (admin)',
                    'en' => 'Contact received (admin)',
                ],
                'subject' => [
                    'es' => 'Nuevo lead de contacto: {{ nombre | "-" }} {{ apellido | "" }}',
                    'en' => 'New contact lead: {{ nombre | "-" }} {{ apellido | "" }}',
                ],
                'preheader' => [
                    'es' => 'Se recibio una nueva consulta desde el formulario de contacto.',
                    'en' => 'A new inquiry was received from the contact form.',
                ],
                'body' => [
                    'es' => '<ul><li><b>Nombre:</b> <span class="nombre">{{ nombre | "-" }}</span></li><li><b>Apellido:</b> <span class="apellido">{{ apellido | "-" }}</span></li><li><b>RUT:</b> <span class="rut">{{ rut | "-" }}</span></li><li><b>Telefono:</b> <a href="tel:{{ telefono | "-" }}"><span class="telefono">{{ telefono | "-" }}</span></a></li><li><b>Email:</b> <a href="mailto:{{ email | "-" }}"><span class="email">{{ email | "-" }}</span></a></li><li><b>Comuna:</b> <span class="comuna">{{ comuna | "-" }}</span></li><li><b>Proyecto:</b> <span class="proyecto">{{ proyecto | "-" }}</span></li><li><b>Medio de llegada:</b> <span class="medio">{{ medio | "Black" }}</span></li><li><b>¿En que rango se encuentra tu renta liquida?:</b> <span class="rango">{{ rango | "-" }}</span></li><li><b>¿Cuentas con posibilidad de codeudor?:</b> <span class="codeudor">{{ codeudor | "-" }}</span></li><li><b>¿Buscas tu nuevo depto para...?:</b> <span class="buscas">{{ buscas | "-" }}</span></li><li><b>¿Cual es tu estado laboral?:</b> <span class="elaboral">{{ elaboral | "-" }}</span></li></ul><p>{{ mensaje | "" }}</p><p>--<br>This e-mail was sent from a contact form on {{ site_name | "iLeben" }} ({{ site_url | "https://sale.ileben.cl" }})</p>',
                    'en' => '<ul><li><b>Name:</b> <span class="nombre">{{ nombre | "-" }}</span></li><li><b>Last name:</b> <span class="apellido">{{ apellido | "-" }}</span></li><li><b>RUT:</b> <span class="rut">{{ rut | "-" }}</span></li><li><b>Phone:</b> <a href="tel:{{ telefono | "-" }}"><span class="telefono">{{ telefono | "-" }}</span></a></li><li><b>Email:</b> <a href="mailto:{{ email | "-" }}"><span class="email">{{ email | "-" }}</span></a></li><li><b>District:</b> <span class="comuna">{{ comuna | "-" }}</span></li><li><b>Project:</b> <span class="proyecto">{{ proyecto | "-" }}</span></li><li><b>Lead source:</b> <span class="medio">{{ medio | "Black" }}</span></li><li><b>Income range:</b> <span class="rango">{{ rango | "-" }}</span></li><li><b>Co-signer available?:</b> <span class="codeudor">{{ codeudor | "-" }}</span></li><li><b>Looking for a new apartment for...?:</b> <span class="buscas">{{ buscas | "-" }}</span></li><li><b>Employment status:</b> <span class="elaboral">{{ elaboral | "-" }}</span></li></ul><p>{{ mensaje | "" }}</p><p>--<br>This e-mail was sent from a contact form on {{ site_name | "iLeben" }} ({{ site_url | "https://sale.ileben.cl" }})</p>',
                ],
                'category' => 'transactional',
                'tags' => ['contact', 'lead', 'admin'],
                'token_schema' => [
                    'nombre' => 'string',
                    'apellido' => 'string',
                    'rut' => 'string',
                    'telefono' => 'string',
                    'email' => 'string',
                    'comuna' => 'string',
                    'proyecto' => 'string',
                    'medio' => 'string',
                    'rango' => 'string',
                    'codeudor' => 'string',
                    'buscas' => 'string',
                    'elaboral' => 'string',
                    'mensaje' => 'string',
                    'site_name' => 'string',
                    'site_url' => 'string',
                ],
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::query()->updateOrCreate(
                ['key' => $template['key']],
                [
                    'name' => $template['name'],
                    'category' => $template['category'],
                    'tags' => $template['tags'],
                    'subject' => $template['subject'],
                    'preheader' => $template['preheader'],
                    'body' => $template['body'],
                    'token_schema' => $template['token_schema'],
                    'is_active' => true,
                    'is_locked' => false,
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     */
    protected function mergeLocale(array $translations, string $locale, string $value): array
    {
        if (! array_key_exists($locale, $translations) || blank($translations[$locale])) {
            $translations[$locale] = $value;
        }

        return $translations;
    }
}
