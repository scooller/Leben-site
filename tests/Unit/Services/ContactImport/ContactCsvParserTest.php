<?php

namespace Tests\Unit\Services\ContactImport;

use App\Services\ContactImport\ContactCsvParser;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContactCsvParserTest extends TestCase
{
    public function test_parse_file_reads_headers_and_rows(): void
    {
        Storage::disk('local')->put(
            'imports/test-contact-parser.csv',
            "Nombre;email;Proyecto\n".
            "Juan Perez;juan@example.com;ICON\n".
            "Ana Soto;ana@example.com;ZEN\n"
        );

        $parsed = app(ContactCsvParser::class)->parseFile(
            filePath: 'imports/test-contact-parser.csv',
            delimiter: ';',
            hasHeader: true,
            maxRows: 500,
        );

        $this->assertSame(['Nombre', 'email', 'Proyecto'], $parsed['headers']);
        $this->assertSame(2, $parsed['total_rows']);
        $this->assertSame('Juan Perez', $parsed['rows'][0]['Nombre']);
        $this->assertSame('ana@example.com', $parsed['rows'][1]['email']);
    }

    public function test_parse_file_throws_when_max_rows_exceeded(): void
    {
        Storage::disk('local')->put(
            'imports/test-contact-parser-max.csv',
            "Nombre,email\nA,a@example.com\nB,b@example.com\n"
        );

        $parsed = app(ContactCsvParser::class)->parseFile(
            filePath: 'imports/test-contact-parser-max.csv',
            delimiter: ',',
            hasHeader: true,
            maxRows: 1,
        );

        $this->assertNotNull($parsed['error']);
        $this->assertStringContainsString('excede el máximo permitido', (string) $parsed['error']);
    }
}
