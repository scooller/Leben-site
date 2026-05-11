<x-filament::card>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Sync oportunidades Salesforce
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Actualiza snapshots locales de pipeline comercial.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="rounded-lg bg-blue-50 p-3 dark:bg-blue-900/20">
                <p class="text-xs font-medium uppercase tracking-wider text-blue-600 dark:text-blue-400">Total</p>
                <p class="mt-1 text-2xl font-bold text-blue-900 dark:text-blue-100">{{ $this->totalOpportunities }}</p>
            </div>
            <div class="rounded-lg bg-amber-50 p-3 dark:bg-amber-900/20">
                <p class="text-xs font-medium uppercase tracking-wider text-amber-600 dark:text-amber-400">Abiertas</p>
                <p class="mt-1 text-2xl font-bold text-amber-900 dark:text-amber-100">{{ $this->openOpportunities }}</p>
            </div>
            <div class="rounded-lg bg-purple-50 p-3 dark:bg-purple-900/20">
                <p class="text-xs font-medium uppercase tracking-wider text-purple-600 dark:text-purple-400">Ultima sync</p>
                <p class="mt-1 text-sm font-semibold text-purple-900 dark:text-purple-100">{{ $this->lastSyncTime }}</p>
            </div>
        </div>

        <button
            wire:click="syncOpportunities"
            wire:loading.attr="disabled"
            wire:target="syncOpportunities"
            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <svg
                wire:loading.class="animate-spin"
                wire:loading.remove.class="hidden"
                wire:target="syncOpportunities"
                class="hidden h-4 w-4"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span wire:loading.remove wire:target="syncOpportunities">Sincronizar ahora</span>
            <span wire:loading wire:target="syncOpportunities">Sincronizando...</span>
        </button>
    </div>
</x-filament::card>
