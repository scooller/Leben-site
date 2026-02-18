<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use Illuminate\Console\Command;

class CleanLogo extends Command
{
    protected $signature = 'site:clean-logo';

    protected $description = 'Remove invalid logo references from database';

    public function handle(): void
    {
        $settings = SiteSetting::first();

        if (! $settings) {
            $this->info('No SiteSetting found. Creating default...');
            $settings = SiteSetting::create(['id' => 1]);
        }

        $settings->update([
            'logo' => null,
            'logo_dark' => null,
        ]);

        $this->info('✓ Logo references cleaned successfully');
    }
}
