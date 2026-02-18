<?php

use App\Models\SiteSetting;

if (! function_exists('site_config')) {
    /**
     * Obtener configuración del sitio
     *
     * @param  string|null  $key  Clave de configuración específica
     * @param  mixed  $default  Valor por defecto si no existe
     */
    function site_config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return SiteSetting::current();
        }

        return SiteSetting::get($key, $default);
    }
}

if (! function_exists('site_name')) {
    /**
     * Obtener el nombre del sitio
     */
    function site_name(): string
    {
        return SiteSetting::get('site_name', config('app.name'));
    }
}

if (! function_exists('site_logo')) {
    /**
     * Obtener la URL del logo
     */
    function site_logo(?string $type = null): ?string
    {
        $key = $type ? "logo_{$type}" : 'logo';
        $logo = SiteSetting::get($key);

        return $logo ? url($logo) : null;
    }
}

if (! function_exists('site_color')) {
    /**
     * Obtener un color del tema
     */
    function site_color(string $name = 'primary'): string
    {
        $defaults = [
            'primary' => '#667eea',
            'secondary' => '#764ba2',
            'background' => '#ffffff',
            'text' => '#1f2937',
        ];

        return SiteSetting::get("{$name}_color", $defaults[$name] ?? '#000000');
    }
}
