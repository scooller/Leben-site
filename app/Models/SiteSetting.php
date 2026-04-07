<?php

namespace App\Models;

use App\Support\LogsModelActivity;
use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use LogsModelActivity;

    protected $fillable = [
        'site_name',
        'site_description',
        'site_url',
        'logo',
        'logo_dark',
        'favicon',
        'icon',
        'logo_id',
        'logo_dark_id',
        'favicon_id',
        'icon_id',
        'webawesome_theme',
        'webawesome_palette',
        'brand_color',
        'semantic_brand_color',
        'semantic_neutral_color',
        'semantic_success_color',
        'semantic_warning_color',
        'semantic_danger_color',
        'icon_family',
        'font_family_body',
        'font_family_heading',
        'google_fonts_stylesheet',
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
        'maintenance_use_html',
        'maintenance_message',
        'extra_settings',
        'gateway_transbank_enabled',
        'gateway_mercadopago_enabled',
        'gateway_manual_enabled',
        'gateway_reservation_timeout_minutes',
        'gateway_transbank_config',
        'gateway_mercadopago_config',
        'gateway_manual_config',
        'banner_image',
        'banner_image_id',
        'banner_link',
        'dashboard_widget_order',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'maintenance_use_html' => 'boolean',
        'gateway_transbank_enabled' => 'boolean',
        'gateway_mercadopago_enabled' => 'boolean',
        'gateway_manual_enabled' => 'boolean',
        'gateway_reservation_timeout_minutes' => 'integer',
        'extra_settings' => 'array',
        'gateway_transbank_config' => 'array',
        'gateway_mercadopago_config' => 'array',
        'gateway_manual_config' => 'array',
        'dashboard_widget_order' => 'array',
    ];

    /**
     * Relaciones con Curator Media
     */
    public function logoMedia()
    {
        return $this->belongsTo(Media::class, 'logo_id');
    }

    public function logoDarkMedia()
    {
        return $this->belongsTo(Media::class, 'logo_dark_id');
    }

    public function iconMedia()
    {
        return $this->belongsTo(Media::class, 'icon_id');
    }

    public function faviconMedia()
    {
        return $this->belongsTo(Media::class, 'favicon_id');
    }

    public function bannerImageMedia()
    {
        return $this->belongsTo(Media::class, 'banner_image_id');
    }

    /**
     * Obtener la configuración (singleton)
     */
    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'site_name' => 'iLeben',
                'webawesome_theme' => 'mellow',
                'webawesome_palette' => 'natural',
                'brand_color' => '#eb0029',
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
        $settings->load(['logoMedia', 'logoDarkMedia', 'faviconMedia', 'iconMedia', 'bannerImageMedia']);

        return [
            'site_name' => $settings->site_name,
            'site_description' => $settings->site_description,
            'site_url' => $settings->site_url,
            'logo' => $settings->logoMedia?->url ?? null,
            'logo_dark' => $settings->logoDarkMedia?->url ?? null,
            'favicon' => $settings->faviconMedia?->url ?? null,
            'icon' => $settings->iconMedia?->url ?? null,
            'webawesome_theme' => $settings->webawesome_theme ?? 'mellow',
            'webawesome_palette' => $settings->webawesome_palette ?? 'natural',
            'brand_color' => $settings->brand_color ?? '#eb0029',
            'semantic_brand_color' => $settings->semantic_brand_color ?? 'blue',
            'semantic_neutral_color' => $settings->semantic_neutral_color ?? 'gray',
            'semantic_success_color' => $settings->semantic_success_color ?? 'green',
            'semantic_warning_color' => $settings->semantic_warning_color ?? 'yellow',
            'semantic_danger_color' => $settings->semantic_danger_color ?? 'red',
            'icon_family' => $settings->icon_family ?? 'classic',
            'font_family_body' => $settings->font_family_body,
            'font_family_heading' => $settings->font_family_heading,
            'google_fonts_stylesheet' => $settings->google_fonts_stylesheet,
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
            'banner' => [
                'image' => $settings->bannerImageMedia?->url ?? null,
                'link' => $settings->banner_link,
            ],
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
                'reservation_timeout_minutes' => $settings->gateway_reservation_timeout_minutes ?? 15,
            ],
        ];
    }
}
