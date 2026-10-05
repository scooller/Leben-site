<?php

namespace App\Console\Commands;

use App\Models\ContactChannel;
use App\Models\SiteSetting;
use App\Services\Salesforce\SalesforceCaseMapper;
use Illuminate\Console\Command;

class SyncContactFormFieldsKeysCommand extends Command
{
    protected $signature = 'contact:sync-form-fields-keys
                            {--dry-run : Muestra los cambios proyectados sin guardarlos en base de datos}
                            {--include-global : Revisa y actualiza también los campos globales de SiteSetting}';

    protected $description = 'Revisa y sincroniza las claves internas (key) de los campos de formulario en canales de contacto (y global) según su mapeo de Salesforce.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $includeGlobal = (bool) $this->option('include-global');

        if ($dryRun) {
            $this->warn('🔍 MODO DRY-RUN: No se guardará ningún cambio en la base de datos.');
        }

        $totalUpdatedChannels = 0;
        $totalChangesCount = 0;

        // 1. Canales de contacto
        $channels = ContactChannel::query()->get();
        $this->info("Analizando {$channels->count()} canal(es) de contacto...");

        foreach ($channels as $channel) {
            $fields = $channel->form_fields;
            if (! is_array($fields) || $fields === []) {
                continue;
            }

            [$updatedFields, $changes] = $this->auditAndRepairFields($fields);

            if ($changes !== []) {
                $totalUpdatedChannels++;
                $totalChangesCount += count($changes);

                $this->newLine();
                $this->info("Canal [{$channel->id}] '{$channel->name}' ({$channel->slug}):");
                foreach ($changes as $change) {
                    $this->line("  - {$change[0]} ({$change[1]}): '{$change[2]}' → '{$change[3]}'");
                }
                $this->table(['Etiqueta', 'Salesforce Field', 'Clave Anterior', 'Clave Nueva / Acción'], $changes);

                if (! $dryRun) {
                    $channel->form_fields = $updatedFields;
                    $channel->save();
                    $this->line("  ✓ Canal '{$channel->name}' actualizado en base de datos.");
                }
            }
        }

        // 2. Configuración Global (SiteSetting)
        if ($includeGlobal) {
            $settings = SiteSetting::current();
            $globalFields = $settings->contact_form_fields;

            if (is_array($globalFields) && $globalFields !== []) {
                [$updatedGlobalFields, $globalChanges] = $this->auditAndRepairFields($globalFields);

                if ($globalChanges !== []) {
                    $totalChangesCount += count($globalChanges);
                    $this->newLine();
                    $this->info('Configuración Global (SiteSetting):');
                    $this->table(['Etiqueta', 'Salesforce Field', 'Clave Anterior', 'Clave Nueva / Acción'], $globalChanges);

                    if (! $dryRun) {
                        $settings->contact_form_fields = $updatedGlobalFields;
                        $settings->save();
                        $this->line('  ✓ SiteSetting contact_form_fields actualizado en base de datos.');
                    }
                } else {
                    $this->line('Configuración Global (SiteSetting): Las claves internas ya están consistentes.');
                }
            }
        }

        $this->newLine();
        if ($totalChangesCount > 0) {
            $this->info($dryRun
                ? "Resumen: Se detectaron {$totalChangesCount} clave(s) para actualizar."
                : "Resumen: {$totalChangesCount} clave(s) actualizadas con éxito.");
        } else {
            $this->info('No se encontraron canales con campos de formulario que requieran corrección.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array{0: string, 1: string, 2: string, 3: string}>}
     */
    private function auditAndRepairFields(array $fields): array
    {
        $changes = [];
        $usedKeys = [];

        foreach ($fields as $index => &$field) {
            if (! is_array($field)) {
                continue;
            }

            $currentKey = trim((string) ($field['key'] ?? ''));
            $sfField = trim((string) ($field['salesforce_field'] ?? ''));
            $label = trim((string) ($field['label'] ?? ''));

            // Deducción recomendada
            $suggestedKey = null;
            if ($sfField !== '') {
                $suggestedKey = SalesforceCaseMapper::defaultKeyForPayloadField($sfField);
            }
            if ($suggestedKey === null && $label !== '') {
                $suggestedKey = SalesforceCaseMapper::normalizeInternalKey($label);
            }
            if ($suggestedKey === null && $currentKey !== '') {
                $suggestedKey = SalesforceCaseMapper::normalizeInternalKey($currentKey);
            }

            $targetKey = $suggestedKey ?: $currentKey;

            // Evitar colisiones dentro del mismo formulario
            if (in_array($targetKey, $usedKeys, true)) {
                $counter = 2;
                while (in_array("{$targetKey}_{$counter}", $usedKeys, true)) {
                    $counter++;
                }
                $targetKey = "{$targetKey}_{$counter}";
            }

            if ($targetKey !== '' && $targetKey !== $currentKey) {
                $changes[] = [
                    $label ?: '(Sin etiqueta)',
                    $sfField ?: '(Sin campo SF)',
                    $currentKey ?: '(Vacío)',
                    $targetKey,
                ];
                $field['key'] = $targetKey;
            }

            if ($targetKey !== '') {
                $usedKeys[] = $targetKey;
            }
        }
        unset($field);

        return [$fields, $changes];
    }
}
