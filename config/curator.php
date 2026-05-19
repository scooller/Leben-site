<?php

declare(strict_types=1);

use App\Filament\Curator\Schemas\MediaForm;

return [
    'curation_formats' => Awcodes\Curator\Enums\PreviewableExtensions::toArray(),
    'default_disk' => env('CURATOR_DEFAULT_DISK', 'curator'),
    'default_directory' => null,
    'default_visibility' => 'public',
    'features' => [
        'curations' => true,
        'file_swap' => true,
        'directory_restriction' => false,
        'preserve_file_names' => false,
        'tenancy' => [
            'enabled' => false,
            'relationship_name' => null,
        ],
    ],
    'glide_token' => env('CURATOR_GLIDE_TOKEN'),
    'model' => App\Models\CuratorMedia::class,
    'path_generator' => null,
    'url_provider' => 'App\\Filament\\Curator\\Providers\\GifSafeGlideUrlProvider',
    'resource' => [
        'label' => 'Media',
        'plural_label' => 'Media',
        'default_layout' => 'grid',
        'navigation' => [
            'group' => null,
            'icon' => 'heroicon-o-photo',
            'sort' => null,
            'should_register' => true,
            'should_show_badge' => false,
        ],
        'resource' => 'App\\Filament\\Curator\\MediaResource',
        'pages' => [
            'create' => App\Filament\Curator\Pages\CreateMedia::class,
            'edit' => App\Filament\Curator\Pages\EditMedia::class,
            'index' => App\Filament\Curator\Pages\ListMedia::class,
        ],
        'schemas' => [
            'form' => MediaForm::class,
        ],
        'tables' => [
            'table' => App\Filament\Curator\Tables\MediaTable::class,
        ],
    ],
];
