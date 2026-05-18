<?php

namespace App\Services\ContactImport;

use Illuminate\Support\Facades\Storage;

class ContactCsvParser
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>, preview: array<int, array<string, string>>, delimiter: string, total_rows: int, error: string|null}
     */
    public function parseFile(string $filePath, ?string $delimiter = null, bool $hasHeader = true, int $maxRows = 500): array
    {
        if (! Storage::disk('local')->exists($filePath)) {
            return $this->errorResult('No se encontró el archivo CSV cargado.');
        }

        $content = (string) Storage::disk('local')->get($filePath);
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $firstLine = (string) ($lines[0] ?? '');
        $detectedDelimiter = $delimiter ?: $this->detectDelimiter($firstLine);

        $headers = [];
        $rows = [];
        $rowCount = 0;

        foreach ($lines as $index => $line) {
            $columns = str_getcsv((string) $line, $detectedDelimiter);

            if ($this->isEmptyCsvRow($columns)) {
                continue;
            }

            if ($index === 0) {
                $columns[0] = $this->stripUtf8Bom((string) ($columns[0] ?? ''));
            }

            $columns = array_values(array_map(
                static fn (mixed $column): string => trim((string) $column),
                $columns,
            ));

            if ($headers === []) {
                if ($hasHeader) {
                    $headers = $this->normalizeHeaders($columns);

                    continue;
                }

                $headers = $this->buildDefaultHeaders(count($columns));
            }

            $rows[] = $this->associateRow($headers, $columns);
            $rowCount++;

            if ($rowCount > $maxRows) {
                return $this->errorResult("El CSV excede el máximo permitido de {$maxRows} filas.");
            }
        }

        if ($headers === []) {
            return $this->errorResult('No se detectaron encabezados ni datos válidos en el CSV.');
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'preview' => array_slice($rows, 0, 5),
            'delimiter' => $detectedDelimiter,
            'total_rows' => $rowCount,
            'error' => null,
        ];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>, preview: array<int, array<string, string>>, delimiter: string, total_rows: int, error: string}
     */
    private function errorResult(string $message): array
    {
        return [
            'headers' => [],
            'rows' => [],
            'preview' => [],
            'delimiter' => ',',
            'total_rows' => 0,
            'error' => $message,
        ];
    }

    private function detectDelimiter(string $firstLine): string
    {
        if (trim($firstLine) === '') {
            return ',';
        }

        $candidates = [',', ';', "\t", '|'];
        $bestDelimiter = ',';
        $bestCount = -1;

        foreach ($candidates as $candidate) {
            $count = substr_count($firstLine, $candidate);

            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $columns
     * @return array<string, string>
     */
    private function associateRow(array $headers, array $columns): array
    {
        $associated = [];

        foreach ($headers as $index => $header) {
            $associated[$header] = trim((string) ($columns[$index] ?? ''));
        }

        return $associated;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $used = [];

        return array_map(function (string $header, int $index) use (&$used): string {
            $baseHeader = trim($header);
            if ($baseHeader === '') {
                $baseHeader = 'columna_'.($index + 1);
            }

            $candidate = $baseHeader;
            $suffix = 2;

            while (in_array(mb_strtolower($candidate), $used, true)) {
                $candidate = $baseHeader.'_'.$suffix;
                $suffix++;
            }

            $used[] = mb_strtolower($candidate);

            return $candidate;
        }, $headers, array_keys($headers));
    }

    /**
     * @return array<int, string>
     */
    private function buildDefaultHeaders(int $count): array
    {
        $headers = [];

        for ($i = 1; $i <= max($count, 1); $i++) {
            $headers[] = 'columna_'.$i;
        }

        return $headers;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $column) {
            if (trim((string) $column) !== '') {
                return false;
            }
        }

        return true;
    }

    private function stripUtf8Bom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }
}
