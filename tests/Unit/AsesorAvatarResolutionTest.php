<?php

namespace Tests\Unit;

use App\Models\Asesor;
use Awcodes\Curator\Models\Media;
use Tests\TestCase;

class AsesorAvatarResolutionTest extends TestCase
{
    public function test_it_returns_salesforce_avatar_when_manual_media_is_missing(): void
    {
        $asesor = new Asesor([
            'avatar_url' => 'https://example.com/salesforce-avatar.jpg',
        ]);

        $this->assertSame('https://example.com/salesforce-avatar.jpg', $asesor->resolved_avatar_url);
    }

    public function test_it_prioritizes_manual_curator_avatar_over_salesforce_avatar(): void
    {
        $asesor = new Asesor([
            'avatar_url' => 'https://example.com/salesforce-avatar.jpg',
        ]);

        $media = new Media;
        $media->disk = 'public';
        $media->path = 'avatars/curator-avatar.jpg';

        $asesor->setRelation('avatarImageMedia', $media);

        $this->assertStringEndsWith('/storage/avatars/curator-avatar.jpg', (string) $asesor->resolved_avatar_url);
    }
}
