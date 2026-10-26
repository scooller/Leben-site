<?php

namespace App\Observers;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\ProjectDetail;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ContentCacheObserver
{
    public function saved(Model $model): void
    {
        $this->clearCacheForModel($model);
    }

    public function deleted(Model $model): void
    {
        $this->clearCacheForModel($model);
    }

    private function clearCacheForModel(Model $model): void
    {
        if ($model instanceof Page) {
            Cache::forget("page_{$model->slug}");
        } elseif ($model instanceof PageSection) {
            if ($model->page) {
                Cache::forget("page_{$model->page->slug}");
            }
        } elseif ($model instanceof Proyecto) {
            Cache::forget("project_content_{$model->id}");
            Cache::forget("project_content_{$model->slug}");
        } elseif ($model instanceof ProjectDetail) {
            if ($model->project) {
                Cache::forget("project_content_{$model->project->id}");
                Cache::forget("project_content_{$model->project->slug}");
            }
        } elseif ($model instanceof Testimonial) {
            Cache::forget('testimonials_all');
            if ($model->project_id) {
                Cache::forget("testimonials_project_{$model->project_id}");
                if ($model->project) {
                    Cache::forget("project_content_{$model->project->id}");
                    Cache::forget("project_content_{$model->project->slug}");
                }
            }
        } elseif ($model instanceof Menu) {
            Cache::forget("menu_location_{$model->location}");
        } elseif ($model instanceof MenuItem) {
            if ($model->menu) {
                Cache::forget("menu_location_{$model->menu->location}");
            }
        } elseif ($model instanceof SiteSetting) {
            Cache::forget('site_settings_current');
        }
    }
}
