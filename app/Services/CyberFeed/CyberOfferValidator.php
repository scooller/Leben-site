<?php

namespace App\Services\CyberFeed;

use App\Models\Plant;
use Illuminate\Support\Facades\Log;

class CyberOfferValidator
{
    /**
     * @var list<array{plant_id: int, sku: string, reason: string, details: string, recorded_at: string}>
     */
    private array $exclusions = [];

    /**
     * Valida si una planta cumple todos los requisitos para publicarse en el Cyber Feed.
     *
     * @param  array{price_uf: float, reference_price_uf: float, image_url: ?string, public_url: ?string, delivery_status: ?string}  $computed
     */
    public function isValid(Plant $plant, array $computed): bool
    {
        $sku = $plant->resolveSku();
        $plantId = (int) $plant->id;

        // 1. Proyecto asociado activo y existente
        if (! $plant->proyecto || ! $plant->proyecto->is_active) {
            $this->recordExclusion($plantId, $sku, 'INACTIVE_OR_MISSING_PROJECT', 'Project is missing or inactive.');

            return false;
        }

        // 2. Comuna y Nombre de Proyecto obligatorios
        if (blank($plant->proyecto->name) || blank($plant->proyecto->comuna)) {
            $this->recordExclusion($plantId, $sku, 'MISSING_PROJECT_DETAILS', 'Project name or commune is empty.');

            return false;
        }

        // 3. Precios en UF obligatorios y coherentes
        $priceUf = $computed['price_uf'];
        $refPriceUf = $computed['reference_price_uf'] ?? $priceUf;

        if ($priceUf <= 0) {
            $this->recordExclusion($plantId, $sku, 'PRICE_ZERO_OR_NEGATIVE', "Effective price_uf is {$priceUf}.");

            return false;
        }

        if ($refPriceUf <= 0) {
            $this->recordExclusion($plantId, $sku, 'REFERENCE_PRICE_ZERO', "Reference price_uf is {$refPriceUf}.");

            return false;
        }

        if ($priceUf > $refPriceUf) {
            $this->recordExclusion($plantId, $sku, 'PRICE_GREATER_THAN_REFERENCE', "price_uf ({$priceUf}) is greater than reference_price_uf ({$refPriceUf}).");

            return false;
        }

        // 4. Imagen obligatoria (debe ser HTTP/HTTPS válida, no data:image ni via.placeholder.com)
        $imageUrl = $computed['image_url'];
        if (blank($imageUrl) || str_starts_with($imageUrl, 'data:') || str_contains($imageUrl, 'via.placeholder.com')) {
            $this->recordExclusion($plantId, $sku, 'MISSING_OR_INVALID_IMAGE', 'Image URL is missing or placeholder.');

            return false;
        }

        // 5. URL pública obligatoria
        $publicUrl = $computed['public_url'];
        if (blank($publicUrl) || ! filter_var($publicUrl, FILTER_VALIDATE_URL)) {
            $this->recordExclusion($plantId, $sku, 'INVALID_PUBLIC_URL', "Public URL '{$publicUrl}' is invalid.");

            return false;
        }

        // 6. Estado de entrega obligatorio
        if (blank($computed['delivery_status'])) {
            $this->recordExclusion($plantId, $sku, 'MISSING_DELIVERY_STATUS', 'Delivery status is missing.');

            return false;
        }

        return true;
    }

    private function recordExclusion(int $plantId, string $sku, string $reason, string $details): void
    {
        $entry = [
            'plant_id' => $plantId,
            'sku' => $sku,
            'reason' => $reason,
            'details' => $details,
            'recorded_at' => now()->toIso8601String(),
        ];

        $this->exclusions[] = $entry;

        Log::channel('single')->warning("[CyberFeedExclusion] {$reason}: Plant ID {$plantId} ({$sku}) - {$details}", $entry);
    }

    /**
     * @return list<array{plant_id: int, sku: string, reason: string, details: string, recorded_at: string}>
     */
    public function getExclusions(): array
    {
        return $this->exclusions;
    }
}
