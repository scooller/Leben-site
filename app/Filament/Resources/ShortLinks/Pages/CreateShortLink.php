<?php

namespace App\Filament\Resources\ShortLinks\Pages;

use App\Enums\ShortLinkStatus;
use App\Filament\Resources\ShortLinks\ShortLinkResource;
use App\Models\ShortLink;
use App\Services\ShortLink\ShortLinkService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateShortLink extends CreateRecord
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
                'page' => 'create',
                'slug' => $slug,
                'slug_exists' => null,
            ]);

            return;
        }

        $slugExists = ShortLink::query()
            ->where('slug', $slug)
            ->exists();

        $this->slugAvailabilityMessage = $slugExists ? 'No disponible' : 'Disponible';
        $this->slugAvailabilityColor = $slugExists ? 'danger' : 'success';

        Log::info('short-link slug availability check', [
            'page' => 'create',
            'slug' => $slug,
            'slug_exists' => $slugExists,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var ShortLinkService $service */
        $service = app(ShortLinkService::class);

        $data['destination_url'] = $service->normalizeAndValidateDestinationUrl((string) ($data['destination_url'] ?? ''));
        $data['slug'] = filled($data['slug'] ?? null)
            ? strtolower((string) $data['slug'])
            : $service->generateUniqueSlug();
        $data['status'] = $data['status'] ?? ShortLinkStatus::ACTIVE->value;
        $data['created_by'] = Auth::id();

        return $data;
    }
}
