<?php

namespace Tests\Unit\Filament;

use Awcodes\Curator\Facades\Curator;
use Tests\TestCase;

class CuratorUploadConfigurationTest extends TestCase
{
    public function test_curator_accepts_images_videos_and_pdf_on_public_visibility(): void
    {
        $acceptedFileTypes = Curator::getAcceptedFileTypes();

        $this->assertContains('image/*', $acceptedFileTypes);
        $this->assertContains('video/*', $acceptedFileTypes);
        $this->assertContains('application/pdf', $acceptedFileTypes);
        $this->assertSame('curator', Curator::getDiskName());
        $this->assertSame('public', Curator::getVisibility());
    }
}
