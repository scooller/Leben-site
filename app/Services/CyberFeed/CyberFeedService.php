<?php

namespace App\Services\CyberFeed;

use App\Models\Plant;
use App\Models\Proyecto;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CyberFeedService
{
    public function __construct(
        protected CyberOfferValidator $validator
    ) {}

    /**
     * Construye y resuelve el feed completo con soporte de filtros y paginación.
     *
     * @param  array<string, mixed>  $filters
     * @return array{store: array<string, mixed>, meta: array<string, mixed>, offers: list<array<string, mixed>>, exclusions_count: int}
     */
    public function buildFeed(array $filters = []): array
    {
        $isCyber = (bool) ($filters['is_cyber'] ?? true);
        $frontendUrl = rtrim(config('app.frontend_sale_url') ?: env('FRONTEND_SALE_URL', 'https://sale.ileben.cl'), '/');
        $validFrom = config('app.cyber_valid_from') ?: env('CYBER_FEED_VALID_FROM', '2026-10-19T00:00:00-03:00');
        $validUntil = config('app.cyber_valid_until') ?: env('CYBER_FEED_VALID_UNTIL', '2026-10-21T23:59:59-03:00');

        $query = $this->buildQuery($filters);

        $perPage = min(max((int) ($filters['per_page'] ?? 100), 1), 250);
        $page = max((int) ($filters['page'] ?? 1), 1);

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $offers = [];
        $latestUpdate = null;

        foreach ($paginator->items() as $plant) {
            /** @var Plant $plant */
            $computed = $this->computePlantAttributes($plant, $frontendUrl, $isCyber);

            // Filtro dinámico de availability si se solicitó
            if (! empty($filters['availability']) && $computed['availability'] !== $filters['availability']) {
                continue;
            }

            if (! $this->validator->isValid($plant, $computed)) {
                continue;
            }

            $updatedAtIso = $plant->updated_at ? $plant->updated_at->toIso8601String() : now()->toIso8601String();
            if ($latestUpdate === null || ($plant->updated_at && $plant->updated_at->isAfter($latestUpdate))) {
                $latestUpdate = $plant->updated_at;
            }

            $offer = [
                'id' => $plant->resolveCanonicalId(),
                'sku' => $plant->resolveSku(),
                'title' => $this->resolveTitle($plant),
                'url' => $computed['public_url'],
                'image' => $computed['image_url'],
                'project' => (string) $plant->proyecto?->name,
                'project_slug' => (string) $plant->proyecto?->slug,
                'commune' => (string) $plant->proyecto?->comuna,
                'city' => (string) ($plant->proyecto?->provincia ?: 'Santiago'),
                'region' => (string) ($plant->proyecto?->region ?: 'Metropolitana'),
                'property_type' => $this->resolvePropertyType($plant),
                'bedrooms' => $this->parseBedrooms((string) $plant->programa),
                'bathrooms' => $this->parseBathrooms((string) $plant->programa2),
                'surface_m2' => (float) ($plant->superficie_util ?: $plant->superficie_total_principal ?: 0),
                'delivery_status' => $computed['delivery_status'],
                'availability' => $computed['availability'],
                'reservation_price_clp' => (int) ($plant->proyecto?->valor_reserva_exigido_defecto_peso ?: 100000),
                'price_uf' => $computed['price_uf'],
            ];

            // En campañas Cyber/Sale se agregan precios de referencia, porcentajes de descuento y vigencia
            if ($isCyber) {
                $offer['reference_price_uf'] = $computed['reference_price_uf'];
                $offer['discount_percent'] = $computed['discount_percent'];
                $offer['valid_from'] = $validFrom;
                $offer['valid_until'] = $validUntil;
            }

            $offer['updated_at'] = $updatedAtIso;

            $offers[] = $offer;
        }

        $storeUpdatedAt = $latestUpdate ? $latestUpdate->toIso8601String() : now()->toIso8601String();

        return [
            'store' => [
                'name' => 'Leben Grupo Inmobiliario',
                'site' => $frontendUrl.'/',
                'currency' => 'CLP',
                'feed_type' => $isCyber ? 'cyber_campaign' : 'canonical_catalog',
                'updated_at' => $storeUpdatedAt,
            ],
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'offers' => $offers,
            'exclusions_count' => count($this->validator->getExclusions()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function buildQuery(array $filters = []): Builder
    {
        $isCyber = (bool) ($filters['is_cyber'] ?? true);

        $query = Plant::query()
            ->with([
                'proyecto',
                'coverImageMedia',
                'interiorImageMedia',
                'activeReservation',
                'completedReservation',
                'completedPayment',
            ])
            ->where('is_active', true)
            ->whereHas('proyecto', function (Builder $q) {
                $q->where('is_active', true);
            });

        // En Cyber feed solo se publican unidades Sale; en feed canónico general se publican todas las activas
        if ($isCyber || ! empty($filters['only_sale'])) {
            $query->where('unidad_sale', true);
        }

        // Filtro por proyecto (slug o id)
        if (! empty($filters['project'])) {
            $projectFilter = trim((string) $filters['project']);
            $query->whereHas('proyecto', function (Builder $q) use ($projectFilter) {
                $q->where('slug', $projectFilter)
                    ->orWhere('name', $projectFilter);
            });
        }

        // Filtro por comuna
        if (! empty($filters['commune'])) {
            $communeFilter = trim((string) $filters['commune']);
            $communeSlug = Str::slug($communeFilter);
            $query->whereHas('proyecto', function (Builder $q) use ($communeFilter, $communeSlug) {
                $q->where('comuna', $communeFilter)
                    ->orWhereRaw("LOWER(REPLACE(TRIM(comuna), ' ', '-')) = ?", [$communeSlug]);
            });
        }

        // Filtro updated_since
        if (! empty($filters['updated_since'])) {
            try {
                $date = Carbon::parse($filters['updated_since']);
                $query->where('updated_at', '>=', $date);
            } catch (\Throwable) {
                // Si la fecha enviada no es parseable, se omite el filtro
            }
        }

        return $query->orderBy('id', 'asc');
    }

    /**
     * @return array{price_uf: float, reference_price_uf: float, discount_percent: float, public_url: string, image_url: ?string, delivery_status: string, availability: string}
     */
    public function computePlantAttributes(Plant $plant, string $frontendUrl, bool $isCyber = true): array
    {
        $refPriceUf = round((float) ($plant->precio_lista ?: $plant->precio_base ?: 0), 2);

        if ($isCyber) {
            $priceUf = round($plant->resolveFinalPrice(), 2);
            $discountPercent = 0.0;
            if ($refPriceUf > 0 && $priceUf < $refPriceUf) {
                $discountPercent = round((($refPriceUf - $priceUf) / $refPriceUf) * 100, 2);
            }
        } else {
            // Feed no-Cyber/Sale: estrictamente precio lista, sin descuentos ni precios alternativos
            $priceUf = $refPriceUf;
            $discountPercent = 0.0;
        }

        $projectSlug = $plant->proyecto?->slug ?: Str::slug((string) ($plant->proyecto?->name ?? ''));
        $unitName = trim((string) ($plant->name ?? ''));
        $publicUrl = "{$frontendUrl}/p/{$projectSlug}/{$unitName}";

        $imageUrl = $this->resolveImageUrl($plant);
        $deliveryStatus = $this->resolveDeliveryStatus($plant->proyecto);
        $availability = $this->resolveAvailability($plant);

        return [
            'price_uf' => $priceUf,
            'reference_price_uf' => $refPriceUf,
            'discount_percent' => $discountPercent,
            'public_url' => $publicUrl,
            'image_url' => $imageUrl,
            'delivery_status' => $deliveryStatus,
            'availability' => $availability,
        ];
    }

    public function resolveImageUrl(Plant $plant): ?string
    {
        $rawUrl = $plant->coverImageMedia?->url
            ?: ($plant->interiorImageMedia?->url
            ?: ($plant->salesforce_interior_image_url
            ?: $plant->proyecto?->image_url));

        if (blank($rawUrl)) {
            return null;
        }

        if (filter_var($rawUrl, FILTER_VALIDATE_URL)) {
            return $rawUrl;
        }

        if (str_starts_with($rawUrl, '/')) {
            return url($rawUrl);
        }

        return url('/storage/'.$rawUrl);
    }

    public function resolveDeliveryStatus(?Proyecto $proyecto): string
    {
        if (! $proyecto) {
            return 'venta_en_verde';
        }

        if ((bool) $proyecto->entrega_inmediata) {
            return 'entrega_inmediata';
        }

        $etapa = strtolower(trim((string) $proyecto->etapa));

        return match ($etapa) {
            'entrega', 'postventa', 'escrituracion' => 'entrega_inmediata',
            'permiso_edificacion', 'preventa' => 'venta_en_blanco',
            'piloto' => 'piloto',
            default => 'venta_en_verde',
        };
    }

    public function resolveAvailability(Plant $plant): string
    {
        if ($plant->isPaid()) {
            return 'sold';
        }

        if ($plant->isReserved()) {
            return 'reserved';
        }

        return 'available';
    }

    public function resolveTitle(Plant $plant): string
    {
        $name = trim((string) ($plant->name ?? ''));
        $prog1 = trim((string) ($plant->programa ?? ''));
        $prog2 = trim((string) ($plant->programa2 ?? ''));
        $projectName = trim((string) ($plant->proyecto?->name ?? ''));
        $commune = trim((string) ($plant->proyecto?->comuna ?? ''));

        $typology = array_filter([$prog1, $prog2]);
        $typeStr = count($typology) > 0 ? ' - '.implode(' + ', $typology) : '';
        $locationStr = array_filter([$projectName, $commune]);
        $locStr = count($locationStr) > 0 ? ' - '.implode(', ', $locationStr) : '';

        return "Depto {$name}{$typeStr}{$locStr}";
    }

    public function resolvePropertyType(Plant $plant): string
    {
        $raw = strtoupper(trim((string) ($plant->tipo_producto ?? '')));

        return match ($raw) {
            'ESTACIONAMIENTO' => 'Estacionamiento',
            'BODEGA' => 'Bodega',
            'OFICINA' => 'Oficina',
            'LOCAL' => 'Local Comercial',
            default => 'Departamento',
        };
    }

    public function parseBedrooms(string $programa): int
    {
        if (preg_match('/(\d+)/', $programa, $matches)) {
            return (int) $matches[1];
        }

        if (stripos($programa, 'studio') !== false || stripos($programa, 'mono') !== false) {
            return 1;
        }

        return 1;
    }

    public function parseBathrooms(string $programa2): int
    {
        if (preg_match('/(\d+)/', $programa2, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }
}
