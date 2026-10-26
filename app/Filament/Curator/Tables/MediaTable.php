<?php

declare(strict_types=1);

namespace App\Filament\Curator\Tables;

use App\Enums\ShortLinkStatus;
use App\Models\ShortLink;
use App\Models\SiteSetting;
use App\Services\ShortLink\ShortLinkService;
use Awcodes\Curator\Components\Tables\CuratorColumn;
use Awcodes\Curator\Models\Media;
use Exception;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MediaTable
{
    /** @throws Exception */
    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();

        return $table
            ->columns(
                $livewire->layoutView === 'grid'
                    ? static::getDefaultGridTableColumns()
                    : static::getDefaultTableColumns(),
            )
            ->searchable(['title', 'caption', 'description'])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->contentGrid(function () use ($livewire): ?array {
                if ($livewire->layoutView === 'grid') {
                    return [
                        'md' => 2,
                        'lg' => 3,
                        'xl' => 4,
                    ];
                }

                return null;
            })
            ->defaultPaginationPageOption(12)
            ->paginationPageOptions([6, 12, 24, 48, 'all'])
            ->recordUrl(null);
    }

    /** @throws Exception */
    public static function getDefaultTableColumns(): array
    {
        return [
            CuratorColumn::make('url')
                ->label(trans('curator::tables.columns.url'))
                ->imageSize(40),
            TextColumn::make('name')
                ->label(trans('curator::tables.columns.name'))
                ->searchable()
                ->sortable(),
            TextColumn::make('ext')
                ->label(trans('curator::tables.columns.ext'))
                ->sortable(),
            TextColumn::make('size')
                ->label(trans('curator::tables.columns.size'))
                ->formatStateUsing(fn ($record): string => \Awcodes\Curator\Facades\Curator::sizeForHumans($record->size))
                ->sortable(),
            TextColumn::make('dimensions')
                ->label(trans('curator::tables.columns.dimensions'))
                ->getStateUsing(fn ($record): ?string => $record->width ? $record->width.'x'.$record->height : null),
            TextColumn::make('disk')
                ->label(trans('curator::tables.columns.disk'))
                ->toggledHiddenByDefault()
                ->toggleable()
                ->sortable(),
            TextColumn::make('directory')
                ->label(trans('curator::tables.columns.directory'))
                ->toggledHiddenByDefault()
                ->toggleable()
                ->sortable(),
            TextColumn::make('created_at')
                ->label(trans('curator::tables.columns.created_at'))
                ->date('Y-m-d')
                ->sortable(),
        ];
    }

    /** @throws Exception */
    public static function getDefaultGridTableColumns(): array
    {
        return [
            View::make('curator::components.tables.grid-column'),
            TextColumn::make('name')
                ->label(trans('curator::tables.columns.name'))
                ->extraAttributes(['style' => 'display: none;'])
                ->searchable()
                ->sortable(),
            TextColumn::make('ext')
                ->label(trans('curator::tables.columns.ext'))
                ->extraAttributes(['style' => 'display: none;'])
                ->sortable(),
            TextColumn::make('directory')
                ->label(trans('curator::tables.columns.directory'))
                ->extraAttributes(['style' => 'display: none;'])
                ->sortable(),
            TextColumn::make('created_at')
                ->label(trans('curator::tables.columns.created_at'))
                ->extraAttributes(['style' => 'display: none;'])
                ->sortable(),
        ];
    }

    public static function resolveMediaQrUrl(Media $media): string
    {
        $shortLink = self::resolveOrCreateMediaShortLink($media);
        $query = http_build_query(self::resolveTrackingQuery($media));

        if ($query === '') {
            return $shortLink->shortUrl();
        }

        return $shortLink->shortUrl().'?'.$query;
    }

    public static function resolveOrCreateMediaShortLink(Media $media): ShortLink
    {
        $destinationUrl = self::resolveMediaDestinationUrl($media);

        $existingShortLink = self::resolveExistingMediaShortLink($media, $destinationUrl);

        if ($existingShortLink instanceof ShortLink) {
            return $existingShortLink;
        }

        $shortLinkService = app(ShortLinkService::class);

        return ShortLink::query()->create([
            'created_by' => Auth::id(),
            'slug' => $shortLinkService->generateUniqueSlug(),
            'title' => sprintf('QR Archivo: %s', $media->getPrettyName()),
            'destination_url' => $destinationUrl,
            'status' => ShortLinkStatus::ACTIVE,
            'metadata' => [
                'origin' => 'media_file_qr',
                'media_id' => $media->id,
                'media_path' => $media->path,
                'media_name' => $media->getPrettyName(),
                'tracking_defaults' => self::resolveTrackingQuery($media),
            ],
        ]);
    }

    private static function resolveExistingMediaShortLink(Media $media, string $destinationUrl): ?ShortLink
    {
        return ShortLink::query()
            ->where('destination_url', $destinationUrl)
            ->where('metadata->origin', 'media_file_qr')
            ->where('metadata->media_id', $media->id)
            ->latest('id')
            ->first();
    }

    private static function resolveMediaDestinationUrl(Media $media): string
    {
        $path = trim((string) $media->path, '/');

        return url('/curator/'.$path);
    }

    /**
     * @return array<string, string>
     */
    private static function resolveTrackingQuery(Media $media): array
    {
        $campaign = trim((string) SiteSetting::get('extra_settings.utm_campaign_default', ''));

        if ($campaign === '') {
            $campaign = 'archivo_qr';
        }

        return [
            'utm_source' => 'archivo',
            'utm_medium' => 'qr',
            'utm_campaign' => $campaign,
            'utm_content' => 'media_'.$media->id,
        ];
    }
}
