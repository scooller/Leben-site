<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateBrandingLogo extends Command
{
    protected $signature = 'branding:generate-logo';

    protected $description = 'Generate a default branding logo';

    public function handle(): void
    {
        // SVG simple como placeholder
        $svg = <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200">
            <rect width="200" height="200" fill="#667eea"/>
            <text x="100" y="110" font-family="Arial, sans-serif" font-size="48" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="middle">
                iL
            </text>
        </svg>
        SVG;

        $filename = 'logo.svg';

        if (Storage::disk('branding')->exists($filename)) {
            $this->info("Logo ya existe: {$filename}");

            return;
        }

        Storage::disk('branding')->put($filename, $svg);

        // Actualizar la referencia en la BD
        $settings = SiteSetting::first() ?? SiteSetting::create(['id' => 1]);
        $logoUrl = Storage::disk('branding')->url($filename);
        $settings->update(['logo' => $filename]);

        $this->info("✓ Logo generado: {$filename}");
        $this->info("✓ URL: {$logoUrl}");
    }
}
