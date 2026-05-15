<?php

declare(strict_types=1);

namespace App\Filament\Curator\Providers;

use Awcodes\Curator\Concerns\UrlProvider;
use Awcodes\Curator\Glide\GlideBuilder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class GifSafeGlideUrlProvider implements UrlProvider
{
    public static function getThumbnailUrl(string $path): string
    {
        if (self::isGifPath($path)) {
            return self::resolveDirectUrl($path);
        }

        return GlideBuilder::make()->width(200)->height(200)->format('webp')->fit('crop')->toUrl($path);
    }

    public static function getMediumUrl(string $path): string
    {
        if (self::isGifPath($path)) {
            return self::resolveDirectUrl($path);
        }

        return GlideBuilder::make()->width(640)->height(640)->format('webp')->fit('crop')->toUrl($path);
    }

    public static function getLargeUrl(string $path): string
    {
        if (self::isGifPath($path)) {
            return self::resolveDirectUrl($path);
        }

        return GlideBuilder::make()->width(1024)->height(1024)->format('webp')->fit('contain')->toUrl($path);
    }

    private static function isGifPath(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'gif';
    }

    private static function resolveDirectUrl(string $path): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk((string) config('curator.default_disk', 'curator'));

        return $disk->url($path);
    }
}
