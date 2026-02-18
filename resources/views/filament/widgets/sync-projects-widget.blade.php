<x-filament::card class="col-span-1 sm:col-span-2">
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    🏢 Sincronizar Proyectos
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Importar proyectos desde Salesforce.
                </p>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="grid grid-cols-2 gap-4">
            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-3">
                <p class="text-xs font-medium text-blue-600 dark:text-blue-400 uppercase tracking-wider">Total de Proyectos</p>
                <p class="mt-1 text-2xl font-bold text-blue-900 dark:text-blue-100">{{ $this->totalProjects }}</p>
            </div>
            <div class="rounded-lg bg-purple-50 dark:bg-purple-900/20 p-3">
                <p class="text-xs font-medium text-purple-600 dark:text-purple-400 uppercase tracking-wider">Última Sync</p>
                <p class="mt-1 text-sm font-semibold text-purple-900 dark:text-purple-100 break-words">{{ $this->lastSyncTime }}</p>
            </div>
        </div>

        <!-- Botón de sincronización -->
        <div class="flex gap-2">
            <button
                wire:click="syncProjects"
                wire:loading.attr="disabled"
                wire:target="syncProjects"
                class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors dark:focus:ring-offset-gray-900"
            >
                <svg
                    wire:loading.class="animate-spin"
                    wire:loading.remove.class="hidden"
                    wire:target="syncProjects"
                    class="hidden h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>

                <svg
                    wire:loading.class="hidden"
                    wire:target="syncProjects"
                    class="h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>

                <span wire:loading.remove wire:target="syncProjects">Sincronizar Ahora</span>
                <span wire:loading.add wire:target="syncProjects" class="hidden">Sincronizando...</span>
            </button>
        </div>
    </div>
</x-filament::card>
