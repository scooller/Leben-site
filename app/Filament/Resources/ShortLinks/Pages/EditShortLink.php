<?php

namespace App\Filament\Resources\ShortLinks\Pages;

use App\Filament\Resources\ShortLinks\ShortLinkResource;
use App\Models\ShortLink;
use App\Services\ShortLink\ShortLinkService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditShortLink extends EditRecord
{
    protected static string $resource = ShortLinkResource::class;

    public ?string $slugAvailabilityMessage = null;

    public ?string $slugAvailabilityColor = null;

    public function checkSlugAvailability(?string $slug): void
    {
        if (blank($slug)) {
            $this->slugAvailabilityMessage = null;
            $this->slugAvailabilityColor = null;

            Log::info('short-link slug availability check', [
                'page' => 'edit',
                'slug' => $slug,
                'record_id' => $this->getRecord()->getKey(),
                'slug_exists' => null,
            ]);

            return;
        }

        $slugExists = ShortLink::query()
            ->where('slug', $slug)
            ->whereKeyNot($this->getRecord()->getKey())
            ->exists();

        $this->slugAvailabilityMessage = $slugExists ? 'No disponible' : 'Disponible';
        $this->slugAvailabilityColor = $slugExists ? 'danger' : 'success';

        Log::info('short-link slug availability check', [
            'page' => 'edit',
            'slug' => $slug,
            'record_id' => $this->getRecord()->getKey(),
            'slug_exists' => $slugExists,
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var ShortLinkService $service */
        $service = app(ShortLinkService::class);

        $data['destination_url'] = $service->normalizeAndValidateDestinationUrl((string) ($data['destination_url'] ?? ''));
        $data['slug'] = strtolower((string) ($data['slug'] ?? ''));

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
