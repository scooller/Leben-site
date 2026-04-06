<?php

namespace App\Services;

use App\Models\Proyecto;
use App\Models\SiteSetting;

/**
 * Servicio para obtener la imagen correcta de un proyecto
 * Sigue la prioridad: imagen del proyecto > portada Salesforce > logo principal > ícono por defecto
 */
class ProjectImageService
{
    /**
     * URL del ícono por defecto
     */
    private const DEFAULT_ICON = 'https://via.placeholder.com/400x300?text=Proyecto';

    /**
     * Obtiene la URL de la imagen para un proyecto
     *
     * @return string URL de la imagen
     */
    public static function getProjectImageUrl(Proyecto $project): string
    {
        if ($project->project_image_id && $project->projectImage) {
            return $project->projectImage->getUrl();
        }

        if (filled($project->salesforce_portada_url)) {
            return (string) $project->salesforce_portada_url;
        }

        $siteSettings = SiteSetting::first();
        if ($siteSettings && $siteSettings->logo_id && $siteSettings->logoMedia) {
            return $siteSettings->logoMedia->getUrl();
        }

        return self::DEFAULT_ICON;
    }

    /**
     * Obtiene la URL de la imagen para un proyecto por ID
     *
     * @return string URL de la imagen
     */
    public static function getProjectImageUrlById(int $projectId): string
    {
        $project = Proyecto::find($projectId);

        if (! $project) {
            // Si el proyecto no existe, devolver ícono por defecto
            return self::DEFAULT_ICON;
        }

        return self::getProjectImageUrl($project);
    }

    /**
     * Obtiene la URL de la imagen para un proyecto por slug
     *
     * @return string URL de la imagen
     */
    public static function getProjectImageUrlBySlug(string $slug): string
    {
        $project = Proyecto::where('slug', $slug)->first();

        if (! $project) {
            return self::DEFAULT_ICON;
        }

        return self::getProjectImageUrl($project);
    }
}
