<?php

namespace App\Console\Commands;

use App\Models\ContactSubmission;
use Illuminate\Console\Command;

class NormalizeContactRangoRentaKey extends Command
{
    protected $signature = 'contact:normalize-rango-renta-key
                            {--dry-run : Muestra cambios sin persistirlos}';

    protected $description = 'Normaliza fields.rango_renta como llave canónica, elimina aliases históricos y corrige phone a solo dígitos.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY RUN] No se guardarán cambios.');
        }

        $processed = 0;
        $updated = 0;

        ContactSubmission::query()
            ->whereNotNull('fields')
            ->orderBy('id')
            ->chunkById(200, function ($records) use (&$processed, &$updated, $dryRun): void {
                foreach ($records as $record) {
                    $processed++;

                    $fields = is_array($record->fields) ? $record->fields : [];

                    [$normalizedFields, $fieldsChanged] = $this->normalizeRangoRentaFields($fields);
                    $normalizedPhone = $this->normalizePhone($record->phone);
                    $phoneChanged = $normalizedPhone !== $record->phone;

                    if (! $fieldsChanged && ! $phoneChanged) {
                        continue;
                    }

                    $updated++;

                    if ($dryRun) {
                        continue;
                    }

                    $record->forceFill([
                        'fields' => $normalizedFields,
                        'phone' => $normalizedPhone,
                    ])->save();
                }
            });

        $this->info("Registros revisados: {$processed}");
        $this->info("Registros actualizados: {$updated}");

        if ($dryRun) {
            $this->line('Finalizado en modo dry-run.');
        } else {
            $this->line('Normalización histórica completada.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function normalizeRangoRentaFields(array $fields): array
    {
        $originalFields = $fields;

        $aliasKeys = [
            'rango_de_renta',
            'en_que_rango_se_encuentra_tu_renta_liquida',
        ];

        $canonicalValue = trim((string) ($fields['rango_renta'] ?? ''));

        if ($canonicalValue === '') {
            foreach ($aliasKeys as $aliasKey) {
                $aliasValue = trim((string) ($fields[$aliasKey] ?? ''));

                if ($aliasValue === '') {
                    continue;
                }

                $canonicalValue = $aliasValue;
                break;
            }
        }

        if ($canonicalValue !== '') {
            $canonicalValue = $this->normalizeDisplayText($canonicalValue);
        }

        foreach ($aliasKeys as $aliasKey) {
            unset($fields[$aliasKey]);
        }

        if ($canonicalValue !== '') {
            $fields['rango_renta'] = $canonicalValue;
        }

        return [$fields, $fields !== $originalFields];
    }

    private function normalizePhone(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = (string) preg_replace('/\D+/', '', (string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeDisplayText(string $value): string
    {
        $normalized = str_replace('_', ' ', trim($value));
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }
}
