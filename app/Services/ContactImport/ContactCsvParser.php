<?php

namespace App\Services\ContactImport;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ContactCsvParser
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>, preview: array<int, array<string, string>>, delimiter: string, total_rows: int, error: string|null}
     */
    public function parseFile(string $filePath, ?string $delimiter = null, bool $hasHeader = true, int $maxRows = 500, string $disk = 'local'): array
    {
        try {
            if (! Storage::disk($disk)->exists($filePath)) {
                return $this->errorResult('No se encontró el archivo CSV cargado.');
            }

            $content = (string) Storage::disk($disk)->get($filePath);

            return $this->parseContent(
                content: $content,
                delimiter: $delimiter,
                hasHeader: $hasHeader,
                maxRows: $maxRows,
            );
        } catch (Throwable $e) {
            Log::warning('Error al leer archivo CSV para importación de contactos: '.$e->getMessage());

            return $this->errorResult('Error al leer el archivo CSV: '.$e->getMessage());
        }
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>, preview: array<int, array<string, string>>, delimiter: string, total_rows: int, error: string|null}
     */
    public function parseContent(string $content, ?string $delimiter = null, bool $hasHeader = true, int $maxRows = 500): array
    {
        try {
            if (trim($content) === '') {
                return $this->errorResult('El archivo CSV está vacío.');
            }

            $utf8Content = $this->ensureUtf8($content);

            $lines = preg_split('/\r\n|\n|\r/', $utf8Content) ?: [];
            $firstLine = (string) ($lines[0] ?? '');
            $detectedDelimiter = $delimiter ?: $this->detectDelimiter($firstLine);

            $headers = [];
            $rows = [];
            $rowCount = 0;

            foreach ($lines as $index => $line) {
                if (trim((string) $line) === '') {
                    continue;
                }

                $columns = str_getcsv((string) $line, $detectedDelimiter);

                if ($this->isEmptyCsvRow($columns)) {
                    continue;
                }

                if ($index === 0) {
                    $columns[0] = $this->stripUtf8Bom((string) ($columns[0] ?? ''));
                }

                $columns = array_values(array_map(
                    fn (mixed $column): string => $this->sanitizeUtf8(trim((string) $column)),
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
        } catch (Throwable $e) {
            Log::warning('Error al procesar contenido CSV para importación de contactos: '.$e->getMessage());

            return $this->errorResult('Error al procesar el contenido CSV: '.$e->getMessage());
        }
    }

    /**
     * Normaliza y convierte el contenido a UTF-8 válido eliminando BOMs y reparando caracteres malformados.
     */
    public function ensureUtf8(string $content): string
    {
        // Detectar y convertir BOMs UTF-16 / UTF-32 / UTF-8
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        } elseif (str_starts_with($content, "\xFF\xFE\x00\x00")) {
            $content = mb_convert_encoding(substr($content, 4), 'UTF-8', 'UTF-32LE');
        } elseif (str_starts_with($content, "\x00\x00\xFE\xFF")) {
            $content = mb_convert_encoding(substr($content, 4), 'UTF-8', 'UTF-32BE');
        } elseif (str_starts_with($content, "\xFF\xFE")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($content, "\xFE\xFF")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        }

        // Si no es UTF-8 válido, detectar codificación común de Excel (Windows-1252/ISO-8859-1) y convertir
        if (! mb_check_encoding($content, 'UTF-8')) {
            $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ISO-8859-15', 'ASCII'], true);
            $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'Windows-1252');
        }

        // Sanitizar secuencias malformadas de bytes restantes
        $clean = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

        if (! mb_check_encoding($clean, 'UTF-8')) {
            $clean = iconv('UTF-8', 'UTF-8//IGNORE', $clean) ?: $clean;
        }

        return $clean;
    }

    /**
     * Sanitiza una cadena individual asegurando UTF-8 válido.
     */
    public function sanitizeUtf8(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            $encoding = mb_detect_encoding($value, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true);
            $value = mb_convert_encoding($value, 'UTF-8', $encoding ?: 'Windows-1252');
        }

        $sanitized = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        if (! mb_check_encoding($sanitized, 'UTF-8')) {
            $sanitized = iconv('UTF-8', 'UTF-8//IGNORE', $sanitized) ?: '';
        }

        return $sanitized;
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
            'error' => $this->sanitizeUtf8($message),
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
            $value = trim((string) ($columns[$index] ?? ''));
            $associated[$header] = $this->sanitizeUtf8($value);
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
            $baseHeader = trim($this->sanitizeUtf8($header));
            if ($baseHeader === '') {
                $baseHeader = 'columna_'.($index + 1);
            }

            $candidate = $baseHeader;
            $suffix = 2;

            while (in_array(mb_strtolower($candidate, 'UTF-8'), $used, true)) {
                $candidate = $baseHeader.'_'.$suffix;
                $suffix++;
            }

            $used[] = mb_strtolower($candidate, 'UTF-8');

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
