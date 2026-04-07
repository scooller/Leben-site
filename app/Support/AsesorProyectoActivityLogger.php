<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Asesor;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AsesorProyectoActivityLogger
{
    public static function logAttached(Asesor $asesor, Collection|EloquentCollection|array $proyectos): void
    {
        $proyectoCollection = static::normalizeProyectoCollection($proyectos);

        if ($proyectoCollection->isEmpty()) {
            return;
        }

        static::log(
            subject: $asesor,
            description: 'Proyectos asignados a asesor',
            properties: [
                'relationship' => 'asesor_proyecto',
                'action' => 'attached',
                'asesor_id' => $asesor->getKey(),
                'proyecto_ids' => $proyectoCollection->pluck('id')->all(),
                'proyecto_names' => $proyectoCollection->pluck('name')->all(),
            ],
        );
    }

    public static function logDetached(Asesor $asesor, Collection|EloquentCollection|array $proyectos): void
    {
        $proyectoCollection = static::normalizeProyectoCollection($proyectos);

        if ($proyectoCollection->isEmpty()) {
            return;
        }

        static::log(
            subject: $asesor,
            description: 'Proyectos removidos de asesor',
            properties: [
                'relationship' => 'asesor_proyecto',
                'action' => 'detached',
                'asesor_id' => $asesor->getKey(),
                'proyecto_ids' => $proyectoCollection->pluck('id')->all(),
                'proyecto_names' => $proyectoCollection->pluck('name')->all(),
            ],
        );
    }

    /**
     * @param  array<int, int>  $attachedAsesorIds
     * @param  array<int, int>  $detachedAsesorIds
     */
    public static function logSynced(Proyecto $proyecto, array $attachedAsesorIds, array $detachedAsesorIds): void
    {
        if ($attachedAsesorIds === [] && $detachedAsesorIds === []) {
            return;
        }

        static::log(
            subject: $proyecto,
            description: 'Asesores sincronizados en proyecto',
            properties: [
                'relationship' => 'asesor_proyecto',
                'action' => 'synced',
                'proyecto_id' => $proyecto->getKey(),
                'attached_asesor_ids' => array_values($attachedAsesorIds),
                'detached_asesor_ids' => array_values($detachedAsesorIds),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private static function log(Model $subject, string $description, array $properties): void
    {
        $logger = activity('asesor_proyecto')->performedOn($subject);

        if (auth()->check()) {
            $logger->causedBy(auth()->user());
        }

        $logger
            ->event('updated')
            ->withProperties($properties)
            ->log($description);
    }

    /**
     * @param  Collection<int, Proyecto>|EloquentCollection<int, Proyecto>|array<int, Proyecto>  $proyectos
     * @return Collection<int, Proyecto>
     */
    private static function normalizeProyectoCollection(Collection|EloquentCollection|array $proyectos): Collection
    {
        return collect($proyectos)
            ->filter(static fn ($proyecto): bool => $proyecto instanceof Proyecto)
            ->values();
    }
}
