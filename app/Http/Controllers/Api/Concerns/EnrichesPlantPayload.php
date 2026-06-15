<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Asesor;
use App\Models\Plant;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use Awcodes\Curator\Models\Media;

trait EnrichesPlantPayload
{
    /**
     * Build the enriched API payload for a plant, including computed fields.
     */
    private function buildPlantPayload(Plant $plant, bool $eventoSale, ?string $discountSource): array
    {
        $payload = $plant->toArray();
        $defaultAdvisorAvatarUrl = $this->getDefaultAdvisorAvatarUrl();
        $apiDiscountPercentage = $this->resolveApiDiscountPercentage($plant, $eventoSale, $discountSource);

        unset($payload['cover_image_id'], $payload['interior_image_id']);

        $payload['cover_image_media'] = $this->mediaPayload($plant->coverImageMedia);
        $payload['interior_image_media'] = $this->mediaPayload($plant->interiorImageMedia);
        $payload['cover_image_url'] = $plant->coverImageMedia?->url;
        $payload['interior_image_url'] = $plant->interiorImageMedia?->url ?: $plant->salesforce_interior_image_url;
        $payload['salesforce_interior_image_url'] = $plant->salesforce_interior_image_url;
        $payload['asesores'] = $this->resolvePlantAdvisors($plant, $defaultAdvisorAvatarUrl);
        $payload['projectLogoUrl'] = $this->resolveProjectLogoUrl($plant);
        $payload['proyectoImageUrl'] = $plant->proyecto?->image_url;
        $payload['imageUrl'] = $this->resolveImageUrl($plant);
        $payload['detailImageUrl'] = $this->resolveDetailImageUrl($plant);
        $payload['is_paid'] = $plant->completedReservation !== null || $plant->completedPayment !== null;
        $payload['is_available'] = $plant->activeReservation === null
            && $plant->completedReservation === null
            && $plant->completedPayment === null;
        $payload['precio_final'] = $this->resolveApiFinalPrice($plant, $apiDiscountPercentage);

        return $payload;
    }

    /**
     * Build a lightweight enriched payload for a plant (used inside proyecto detail).
     */
    private function buildCompactPlantPayload(Plant $plant, bool $eventoSale, ?string $discountSource, ?string $defaultAdvisorAvatarUrl): array
    {
        $payload = $plant->toArray();
        $apiDiscountPercentage = $this->resolveApiDiscountPercentage($plant, $eventoSale, $discountSource);

        unset($payload['cover_image_id'], $payload['interior_image_id']);

        $payload['cover_image_url'] = $plant->coverImageMedia?->url;
        $payload['interior_image_url'] = $plant->interiorImageMedia?->url ?: $plant->salesforce_interior_image_url;
        $payload['imageUrl'] = $this->resolveImageUrl($plant);
        $payload['detailImageUrl'] = $this->resolveDetailImageUrl($plant);
        $payload['descuento_defecto_cotizacion_web'] = $plant->descuento_defecto_cotizacion_web
            ?? $plant->proyecto?->descuento_defecto_cotizacion_web;
        $payload['asesores'] = $this->resolvePlantAdvisors($plant, $defaultAdvisorAvatarUrl ?? $this->getDefaultAdvisorAvatarUrl());
        $payload['is_paid'] = $plant->completedReservation !== null || $plant->completedPayment !== null;
        $payload['is_available'] = $plant->activeReservation === null
            && $plant->completedReservation === null
            && $plant->completedPayment === null;
        $payload['precio_final'] = $this->resolveApiFinalPrice($plant, $apiDiscountPercentage);

        return $payload;
    }

    private function resolveApiDiscountPercentage(Plant $plant, bool $eventoSale, ?string $discountSource): float
    {
        if ($discountSource === 'project') {
            return (float) ($plant->proyecto?->descuento_maximo_unidad ?? $plant->porcentaje_maximo_unidad ?? 0);
        }

        if ($discountSource === 'plant') {
            return (float) ($plant->porcentaje_maximo_unidad ?? $plant->proyecto?->descuento_maximo_unidad ?? 0);
        }

        $projectDiscount = $plant->proyecto?->descuento_defecto_cotizacion_web;

        return $eventoSale
            ? (float) ($plant->porcentaje_maximo_unidad ?? 0)
            : (float) ($projectDiscount ?? 0);
    }

    private function resolveApiFinalPrice(Plant $plant, float $discountPercentage): float
    {
        $precioLista = (float) ($plant->precio_lista ?? 0);
        $precioBase = (float) ($plant->precio_base ?? 0);

        if ($precioLista > 0 && $discountPercentage > 0) {
            $precioConDescuento = $precioLista - (($precioLista * $discountPercentage) / 100);

            return max(0, $precioConDescuento);
        }

        return max(0, $precioBase);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolvePlantAdvisors(Plant $plant, string $defaultAdvisorAvatarUrl): array
    {
        if ($plant->asesor !== null && (bool) $plant->asesor->is_active) {
            return [$this->asesorPayload($plant->asesor, $defaultAdvisorAvatarUrl)];
        }

        return $plant->proyecto?->asesores
            ->where('is_active', true)
            ->values()
            ->map(fn (Asesor $asesor): array => $this->asesorPayload($asesor, $defaultAdvisorAvatarUrl))
            ->all() ?? [];
    }

    private function resolveImageUrl(Plant $plant): string
    {
        if ($plant->coverImageMedia?->url) {
            return $plant->coverImageMedia->url;
        }

        if ($plant->proyecto?->image_url) {
            return $plant->proyecto->image_url;
        }

        $siteSettings = SiteSetting::first();
        if ($siteSettings?->logoMedia?->url) {
            return $siteSettings->logoMedia->url;
        }

        return $this->getDefaultImageUrl();
    }

    private function resolveDetailImageUrl(Plant $plant): string
    {
        if ($plant->interiorImageMedia?->url) {
            return $plant->interiorImageMedia->url;
        }

        if (filled($plant->salesforce_interior_image_url)) {
            return (string) $plant->salesforce_interior_image_url;
        }

        return $this->resolveImageUrl($plant);
    }

    private function resolveProjectLogoUrl(Plant $plant): ?string
    {
        if (filled($plant->proyecto?->salesforce_logo_url)) {
            return (string) $plant->proyecto->salesforce_logo_url;
        }

        return null;
    }

    private function getDefaultImageUrl(): string
    {
        return 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 300%22%3E%3Crect fill=%22%23e5e7eb%22 width=%22400%22 height=%22300%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2248%22 fill=%22%239ca3af%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22 font-family=%22system-ui%22%3EPlanta%3C/text%3E%3C/svg%3E';
    }

    private function getDefaultAdvisorAvatarUrl(): string
    {
        $siteSettings = SiteSetting::query()->with('logoMedia', 'faviconMedia')->first();

        if (filled($siteSettings?->faviconMedia?->url)) {
            return (string) $siteSettings->faviconMedia->url;
        }

        if (filled($siteSettings?->logoMedia?->url)) {
            return (string) $siteSettings->logoMedia->url;
        }

        if (filled($siteSettings?->logo)) {
            return (string) $siteSettings->logo;
        }

        return 'data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M320 312C253.7 312 200 258.3 200 192C200 125.7 253.7 72 320 72C386.3 72 440 125.7 440 192C440 258.3 386.3 312 320 312zM289.5 368L350.5 368C360.2 368 368 375.8 368 385.5C368 389.7 366.5 393.7 363.8 396.9L336.4 428.9L367.4 544L368 544L402.6 405.5C404.8 396.8 413.7 391.5 422.1 394.7C484 418.3 528 478.3 528 548.5C528 563.6 515.7 575.9 500.6 575.9L139.4 576C124.3 576 112 563.7 112 548.6C112 478.4 156 418.4 217.9 394.8C226.3 391.6 235.2 396.9 237.4 405.6L272 544.1L272.6 544.1L303.6 429L276.2 397C273.5 393.8 272 389.8 272 385.6C272 375.9 279.8 368.1 289.5 368.1z"/></svg>');
    }

    private function mediaPayload(?Media $media): ?array
    {
        if (! $media) {
            return null;
        }

        return [
            'type' => $media->type,
            'title' => $media->title,
            'url' => $media->url,
            'thumbnail_url' => $media->thumbnail_url,
            'medium_url' => $media->medium_url,
            'large_url' => $media->large_url,
        ];
    }

    private function asesorPayload(Asesor $asesor, string $defaultAvatarUrl): array
    {
        $manualAvatarUrl = $asesor->avatarImageMedia?->url;

        return [
            'id' => $asesor->id,
            'full_name' => $asesor->full_name,
            'first_name' => $asesor->first_name,
            'last_name' => $asesor->last_name,
            'email' => $asesor->email,
            'whatsapp_owner' => $asesor->whatsapp_owner,
            'whatsapp_redirect_url' => route('advisors.whatsapp.redirect', ['asesor' => $asesor]),
            'avatar_manual_url' => $manualAvatarUrl,
            'avatar_url' => $manualAvatarUrl ?: $defaultAvatarUrl,
        ];
    }
}
