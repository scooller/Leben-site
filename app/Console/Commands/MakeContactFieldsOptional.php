<?php

namespace App\Console\Commands;

use App\Models\ContactChannel;
use App\Models\SiteSetting;
use Illuminate\Console\Command;

class MakeContactFieldsOptional extends Command
{
    protected $signature = 'contact:make-fields-optional
                            {--keys=codeudor,buscas,elaboral : Comma-separated field keys to make optional}
                            {--dry-run : Show what would change without saving}';

    protected $description = 'Set specific contact form fields as non-required in site_settings and all contact channels.';

    public function handle(): int
    {
        $keys = array_filter(array_map('trim', explode(',', (string) $this->option('keys'))));
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be saved.');
        }

        $this->info('Target fields: '.implode(', ', $keys));

        // --- Global site_settings ---
        $settings = SiteSetting::current();
        $fields = $settings->contact_form_fields ?? [];
        $changed = $this->applyOptional($fields, $keys);

        if ($changed > 0) {
            $this->line("site_settings: {$changed} field(s) updated.");
            if (! $dryRun) {
                $settings->contact_form_fields = $fields;
                $settings->save();
            }
        } else {
            $this->line('site_settings: no matching required fields found.');
        }

        // --- Contact channels ---
        $totalChannels = 0;
        foreach (ContactChannel::all() as $channel) {
            $channelFields = $channel->form_fields ?? [];
            if (empty($channelFields)) {
                continue;
            }
            $channelChanged = $this->applyOptional($channelFields, $keys);
            if ($channelChanged > 0) {
                $this->line("Channel [{$channel->slug}]: {$channelChanged} field(s) updated.");
                if (! $dryRun) {
                    $channel->form_fields = $channelFields;
                    $channel->save();
                }
                $totalChannels++;
            }
        }

        if ($totalChannels === 0) {
            $this->line('Channels: no matching required fields found.');
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Done.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<int, string>  $keys
     */
    private function applyOptional(array &$fields, array $keys): int
    {
        $changed = 0;
        foreach ($fields as &$field) {
            if (in_array($field['key'] ?? '', $keys, true) && ($field['required'] ?? false)) {
                $field['required'] = false;
                $this->line("  → {$field['key']}: required=false");
                $changed++;
            }
        }
        unset($field);

        return $changed;
    }
}
