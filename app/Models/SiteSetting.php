<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    protected $fillable = [
        'site_name',
        'site_description',
        'site_url',
        'logo',
        'logo_dark',
        'favicon',
        'icon',
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'text_color',
        'meta_keywords',
        'meta_author',
        'og_image',
        'contact_email',
        'contact_phone',
        'contact_address',
        'facebook_url',
        'instagram_url',
        'twitter_url',
        'linkedin_url',
        'youtube_url',
        'custom_css',
        'custom_js',
        'header_scripts',
        'footer_scripts',
        'maintenance_mode',
        'maintenance_message',
        'extra_settings',
        'gateway_transbank_enabled',
        'gateway_mercadopago_enabled',
        'gateway_manual_enabled',
        'gateway_transbank_config',
        'gateway_mercadopago_config',
        'gateway_manual_config',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'gateway_transbank_enabled' => 'boolean',
        'gateway_mercadopago_enabled' => 'boolean',
        'gateway_manual_enabled' => 'boolean',
        'extra_settings' => 'array',
        'gateway_transbank_config' => 'array',
        'gateway_mercadopago_config' => 'array',
        'gateway_manual_config' => 'array',
    ];

    /**
     * Obtener la configuración (singleton)
     */
    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'site_name' => 'iLeben',
                'primary_color' => '#667eea',
                'secondary_color' => '#764ba2',
            ]
        );
    }

    /**
     * Obtener un valor específico de configuración
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::current()->{$key} ?? $default;
    }

    /**
     * Establecer un valor de configuración
     */
    public static function set(string $key, mixed $value): void
    {
        $settings = static::current();
        $settings->update([$key => $value]);
    }

    /**
     * Obtener todas las configuraciones como array
     */
    public static function allSettings(): array
    {
        return static::current()->toArray();
    }

    /**
     * Obtener configuraciones públicas para el frontend
     */
    public static function forFrontend(): array
    {
        $settings = static::current();

        return [
            'site_name' => $settings->site_name,
            'site_description' => $settings->site_description,
            'site_url' => $settings->site_url,
            'logo' => $settings->logo ? Storage::disk('branding')->url($settings->logo) : null,
            'logo_dark' => $settings->logo_dark ? Storage::disk('branding')->url($settings->logo_dark) : null,
            'favicon' => $settings->favicon ? Storage::disk('branding')->url($settings->favicon) : null,
            'icon' => $settings->icon ? Storage::disk('branding')->url($settings->icon) : null,
            'theme' => [
                'primary_color' => $settings->primary_color,
                'secondary_color' => $settings->secondary_color,
                'accent_color' => $settings->accent_color,
                'background_color' => $settings->background_color,
                'text_color' => $settings->text_color,
            ],
            'seo' => [
                'meta_keywords' => $settings->meta_keywords,
                'meta_author' => $settings->meta_author,
                'og_image' => $settings->og_image ? url($settings->og_image) : null,
            ],
            'contact' => [
                'email' => $settings->contact_email,
                'phone' => $settings->contact_phone,
                'address' => $settings->contact_address,
            ],
            'social' => [
                'facebook' => $settings->facebook_url,
                'instagram' => $settings->instagram_url,
                'twitter' => $settings->twitter_url,
                'linkedin' => $settings->linkedin_url,
                'youtube' => $settings->youtube_url,
            ],
            'custom_css' => $settings->custom_css,
            'maintenance_mode' => $settings->maintenance_mode,
            'maintenance_message' => $settings->maintenance_message,
            'payment_gateways' => [
                'transbank' => [
                    'enabled' => $settings->gateway_transbank_enabled,
                    'config' => $settings->gateway_transbank_config ?? [],
                ],
                'mercadopago' => [
                    'enabled' => $settings->gateway_mercadopago_enabled,
                    'config' => $settings->gateway_mercadopago_config ?? [],
                ],
                'manual' => [
                    'enabled' => $settings->gateway_manual_enabled,
                    'config' => $settings->gateway_manual_config ?? [],
                ],
            ],
        ];
    }
}
