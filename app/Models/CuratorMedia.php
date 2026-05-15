<?php

declare(strict_types=1);

namespace App\Models;

use Awcodes\Curator\Facades\Curator;
use Awcodes\Curator\Facades\Glide;
use Awcodes\Curator\Models\Media as BaseMedia;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Cache;

class CuratorMedia extends BaseMedia
{
    public function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->isGif()
                ? $this->url
                : Curator::getUrlProvider()::getThumbnailUrl($this->path),
        );
    }

    public function mediumUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->isGif()
                ? $this->url
                : Curator::getUrlProvider()::getMediumUrl($this->path),
        );
    }

    public function largeUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->isGif()
                ? $this->url
                : Curator::getUrlProvider()::getLargeUrl($this->path),
        );
    }

    public function placeholder(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->isGif()) {
                    return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
                }

                $key = 'placeholder:'.$this->name.filemtime($this->full_path);

                return Cache::rememberForever($key, function (): string {
                    $glideApi = Glide::getServer()->getApi();
                    $manager = call_user_func([$glideApi, 'getImageManager']);
                    $image = $manager->read($this->full_path);
                    $placeholder = $image->scaleDown(400)->blur(10)->toJpeg(30)->toString();

                    return 'data:image/jpeg;base64,'.base64_encode($placeholder);
                });
            },
        );
    }

    private function isGif(): bool
    {
        return strtolower((string) $this->ext) === 'gif';
    }
}
