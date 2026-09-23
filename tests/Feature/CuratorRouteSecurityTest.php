<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CuratorRouteSecurityTest extends TestCase
{
    public function test_curator_route_serves_valid_public_file(): void
    {
        Storage::disk('public')->put('test-sample.txt', 'sample public content');

        $response = $this->get('/curator/test-sample.txt');

        $response->assertOk();
        $this->assertSame('sample public content', $response->streamedContent());

        Storage::disk('public')->delete('test-sample.txt');
    }

    public function test_curator_route_blocks_path_traversal(): void
    {
        // Attempting to read .env via directory traversal
        $response = $this->get('/curator/../../.env');
        $response->assertNotFound();

        // Attempting with URL-encoded traversal
        $response = $this->get('/curator/..%2f..%2f.env');
        $response->assertNotFound();
    }
}
