<?php

namespace App\Services\ContactImport;

use App\Models\Proyecto;
use Illuminate\Support\Str;

class ContactTextHomologationService
{
    /**
     * @param  array<string, string>  $fields
     * @return array{fields: array<string, string>, warnings: array<int, string>}
     */
    public function homologate(array $fields, bool $homologateComuna = true, bool $homologateProyecto = true): array
    {
        $warnings = [];

        if ($homologateComuna && filled($fields['comuna'] ?? null)) {
            $inputComuna = (string) $fields['comuna'];
            $homologatedComuna = $this->resolveComuna($inputComuna);

            if ($homologatedComuna !== null) {
                $fields['comuna'] = $homologatedComuna;
            } else {
                $warnings[] = 'No se pudo homologar la comuna: ' . $inputComuna;
            }
        }

        if ($homologateProyecto && filled($fields['proyecto'] ?? null)) {
            $inputProyecto = (string) $fields['proyecto'];
            $homologatedProyecto = $this->resolveProyecto($inputProyecto);

            if ($homologatedProyecto !== null) {
                $fields['proyecto'] = $homologatedProyecto;
            } else {
                $warnings[] = 'No se pudo homologar el proyecto: ' . $inputProyecto;
            }
        }

        return [
            'fields' => $fields,
            'warnings' => $warnings,
        ];
    }

    public function resolveComuna(string $value): ?string
    {
        $signature = $this->textSignature($value);

        foreach ($this->knownComunas() as $comuna) {
            if ($this->textSignature($comuna) === $signature) {
                return $comuna;
            }
        }

        return null;
    }

    public function resolveProyecto(string $value): ?string
    {
        $rawValue = trim($value);

        if ($rawValue === '') {
            return null;
        }

        $bySalesforceId = Proyecto::query()
            ->where('salesforce_id', $rawValue)
            ->orWhereRaw('LOWER(salesforce_id) = ?', [mb_strtolower($rawValue)])
            ->value('name');

        if (is_string($bySalesforceId) && trim($bySalesforceId) !== '') {
            return trim($bySalesforceId);
        }

        $signature = $this->textSignature($rawValue);

        foreach ($this->knownProyectos() as $proyecto) {
            if ($this->textSignature($proyecto) === $signature) {
                return $proyecto;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function knownComunas(): array
    {
        return Proyecto::query()
            ->where('comuna', '!=', null)
            ->pluck('comuna')
            ->map(static fn(string $value): string => trim($value))
            ->filter(static fn(string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function knownProyectos(): array
    {
        return Proyecto::query()
            ->pluck('name')
            ->map(static fn(string $value): string => trim($value))
            ->filter(static fn(string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function textSignature(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replace(':', ' ')
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
