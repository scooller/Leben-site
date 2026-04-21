<?php

namespace App\Filament\Pages;

use AlizHarb\ActivityLog\Pages\UserActivitiesPage as BaseUserActivitiesPage;
use App\Models\User;
use Illuminate\Support\Collection;

class UserActivitiesPage extends BaseUserActivitiesPage
{
    protected string $view = 'filament.pages.user-activities-page';

    /**
     * @return Collection<int, User>
     */
    public function getRecentSpatieUsers(): Collection
    {
        $limit = (int) config('filament-activity-log.pages.user_activities.new_users.limit', 10);

        return User::query()
            ->whereHas('roles')
            ->with('roles:id,name')
            ->latest()
            ->limit(max($limit, 1))
            ->get(['id', 'name', 'email', 'created_at']);
    }
}
