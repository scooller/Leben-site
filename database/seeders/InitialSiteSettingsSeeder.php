<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class InitialSiteSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $setting = SiteSetting::current();

        $extra = is_array($setting->extra_settings) ? $setting->extra_settings : [];

        $extra['company_razon_social'] = 'Inmobiliaria Cenit Limitada';
        $extra['company_rut'] = '76.065.957-6';
        $extra['company_representante_legal'] = 'Juan Ignacio Pinto';
        $extra['company_rut_representante'] = '15.378.760-3';
        $extra['company_city'] = 'Providencia, Santiago, Chile';
        $extra['company_schedule'] = [
            'Lun a Vie: 09:00 a 14:00 hrs',
            'y 15:00 a 18:00 hrs',
        ];
        $extra['footer_copyright'] = '© 2026 Leben. Todos los derechos reservados.';
        $extra['announcement_enabled'] = false;
        $extra['announcement_id'] = 'aviso-atencion-especial-2026';
        $extra['announcement_title'] = 'Horario Especial de Atención';
        $extra['announcement_message'] = 'Informamos a nuestros clientes e inversionistas que durante este periodo atenderemos vía WhatsApp y canal digital.';
        $extra['announcement_button_text'] = 'Entendido';

        $setting->update([
            'contact_phone' => '+569 9129 7804',
            'contact_email' => 'info@ileben.cl',
            'contact_address' => 'Dr. Manuel Barros Borgoño 386',
            'facebook_url' => 'https://www.facebook.com/inmobiliarialeben',
            'instagram_url' => 'https://www.instagram.com/inmobiliarialeben',
            'linkedin_url' => 'https://www.linkedin.com/company/ileben/posts/?feedView=all',
            'footer_legal_text' => 'La promoción se encuentra adscrita en las Bases de promoción protocolizadas en Notaría San Martín N° 17917-2025.',
            'extra_settings' => $extra,
        ]);
    }
}
