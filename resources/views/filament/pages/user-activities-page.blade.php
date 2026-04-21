<x-filament-panels::page>
    <div class="space-y-6">
        <div class="activity-log-card">
            <div class="activity-log-body">
                <div class="flex items-start gap-x-3">
                    <div class="activity-log-icon-wrapper">
                        <x-filament::icon
                            icon="heroicon-o-user-plus"
                            class="activity-log-icon-lg activity-log-text-gray"
                        />
                    </div>
                    <div class="flex-1">
                        <h3 class="activity-log-user">Usuarios nuevos (Spatie)</h3>

                        <div class="mt-3 space-y-2">
                            @forelse ($this->getRecentSpatieUsers() as $user)
                                <div class="flex flex-col gap-1 rounded-lg border border-gray-200 p-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="font-medium text-gray-950 dark:text-gray-100">{{ $user->name }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $user->email }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Roles: {{ $user->roles->pluck('name')->implode(', ') }}
                                        </p>
                                    </div>

                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $user->created_at?->diffForHumans() }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    No hay usuarios con roles de Spatie registrados todavía.
                                </p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="activity-log-card">
            <div class="activity-log-body">
                <div class="flex items-start gap-x-3">
                    <div class="activity-log-icon-wrapper">
                        <x-filament::icon
                            icon="heroicon-o-information-circle"
                            class="activity-log-icon-lg activity-log-text-gray"
                        />
                    </div>
                    <div class="flex-1">
                        <h3 class="activity-log-user">
                            {{ __('filament-activity-log::activity.pages.user_activities.description_title') }}
                        </h3>
                        <div class="activity-log-description">
                            {{ __('filament-activity-log::activity.pages.user_activities.description') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>