<?php

namespace Tests\Feature;

use App\Filament\Curator\Tables\MediaTable;
use App\Jobs\RecordShortLinkVisitJob;
use App\Models\ShortLink;
use App\Models\User;
use Awcodes\Curator\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MediaQrShortLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_media_short_link_and_reuses_it(): void
    {
        $media = Media::query()->create([
            'disk' => 'public',
            'directory' => 'docs',
            'visibility' => 'public',
            'name' => 'brochure',
            'path' => 'docs/brochure.pdf',
            'type' => 'application/pdf',
            'ext' => 'pdf',
        ]);

        $first = MediaTable::resolveOrCreateMediaShortLink($media);
        $second = MediaTable::resolveOrCreateMediaShortLink($media);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseHas('short_links', [
            'id' => $first->id,
            'destination_url' => url('/curator/docs/brochure.pdf'),
        ]);
    }

    public function test_it_generates_qr_url_with_utm_and_tracks_redirect(): void
    {
        Queue::fake();

        $media = Media::query()->create([
            'disk' => 'public',
            'directory' => 'docs',
            'visibility' => 'public',
            'name' => 'terms',
            'path' => 'docs/terms.xml',
            'type' => 'application/xml',
            'ext' => 'xml',
        ]);

        $qrUrl = MediaTable::resolveMediaQrUrl($media);
        $shortLink = ShortLink::query()->where('metadata->origin', 'media_file_qr')->first();

        $this->assertNotNull($shortLink);
        $this->assertStringContainsString('/s/'.$shortLink?->slug, $qrUrl);
        $this->assertStringContainsString('utm_source=archivo', $qrUrl);
        $this->assertStringContainsString('utm_medium=qr', $qrUrl);

        $response = $this->get($qrUrl);

        $response->assertRedirect(url('/curator/docs/terms.xml').'?utm_source=archivo&utm_medium=qr&utm_campaign=archivo_qr&utm_content=media_'.$media->id);

        Queue::assertPushed(RecordShortLinkVisitJob::class, function (RecordShortLinkVisitJob $job): bool {
            return ($job->payload['utm_source'] ?? null) === 'archivo'
                && ($job->payload['utm_medium'] ?? null) === 'qr';
        });
    }

    public function test_media_uploader_has_friendly_uploaded_error_message(): void
    {
        $uploader = \App\Filament\Curator\Schemas\MediaForm::getUploaderField();

        $this->assertSame(
            'El archivo no se pudo subir. Si pesa mas de lo permitido, aumenta upload_max_filesize y post_max_size en el servidor.',
            $uploader->getValidationMessages()['uploaded'] ?? null,
        );
    }

    public function test_media_edit_page_shows_qr_section(): void
    {
        $this->actingAs(User::factory()->create([
            'user_type' => 'admin',
        ]));

        $media = Media::query()->create([
            'disk' => 'public',
            'directory' => 'docs',
            'visibility' => 'public',
            'name' => 'contract',
            'path' => 'docs/contract.pdf',
            'type' => 'application/pdf',
            'ext' => 'pdf',
            'size' => 3145728,
        ]);

        $this->get(\App\Filament\Curator\MediaResource::getUrl('edit', ['record' => $media]))
            ->assertOk()
            ->assertSee('Ver QR');
    }
}
