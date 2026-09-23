@php
    $rawTitle = trim((string) ($title ?? ''));
    $rawDesc = trim((string) ($description ?? ''));
    $url = rtrim((string) ($siteUrl ?? 'https://sale.ileben.cl'), '/');
    $name = (string) ($siteName ?? 'iLeben');
    $locale = (string) ($locale ?? 'es-CL');

    $displayTitle = $rawTitle !== '' ? $rawTitle : "{$name} | Departamentos y Proyectos en Venta";
    $displayDesc = $rawDesc !== '' ? $rawDesc : 'Descubre departamentos inmobiliarios disponibles para compra en Chile. Cotiza online con asesoría personalizada.';

    $titleLen = mb_strlen($displayTitle);
    $descLen = mb_strlen($displayDesc);

    // Google limits ~60 chars for title, ~160 chars for description
    $titleStatus = $titleLen <= 60 ? 'text-success-600 dark:text-success-400' : 'text-amber-600 dark:text-amber-400';
    $descStatus = ($descLen >= 120 && $descLen <= 160) ? 'text-success-600 dark:text-success-400' : ($descLen < 120 ? 'text-gray-500' : 'text-amber-600 dark:text-amber-400');
@endphp

<div x-data="{ mode: 'desktop' }" class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm space-y-4">
    <!-- Header with Desktop / Mobile Toggle and Hreflang badge -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800 pb-3">
        <div class="flex items-center space-x-2">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                SERP Snippet Preview (Google Search)
            </span>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                hreflang: {{ $locale }} &bull; x-default
            </span>
        </div>

        <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-0.5 text-xs">
            <button
                type="button"
                @click="mode = 'desktop'"
                :class="mode === 'desktop' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm font-medium' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                class="px-2.5 py-1 rounded-md transition-colors flex items-center space-x-1"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                <span>Desktop</span>
            </button>
            <button
                type="button"
                @click="mode = 'mobile'"
                :class="mode === 'mobile' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm font-medium' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                class="px-2.5 py-1 rounded-md transition-colors flex items-center space-x-1"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                <span>Mobile</span>
            </button>
        </div>
    </div>

    <!-- Live Preview Container -->
    <div class="py-2">
        <!-- Desktop Container -->
        <div x-show="mode === 'desktop'" class="max-w-[600px] font-sans">
            <!-- URL / Breadcrumb -->
            <div class="flex items-center space-x-2 text-xs mb-1">
                <div class="w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500 overflow-hidden shrink-0 border border-gray-200 dark:border-gray-700">
                    <img src="{{ $url }}/favicon.ico" alt="Favicon" class="w-4 h-4" onerror="this.style.display='none'" />
                </div>
                <div class="leading-tight truncate">
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $name }}</span>
                    <span class="text-gray-500 dark:text-gray-400 block truncate text-[11px]">{{ $url }}</span>
                </div>
            </div>

            <!-- Title -->
            <h3 class="text-[20px] leading-snug font-normal text-[#1a0dab] dark:text-[#8ab4f8] hover:underline cursor-pointer truncate mb-1">
                {{ $displayTitle }}
            </h3>

            <!-- Snippet Description -->
            <p class="text-[14px] leading-relaxed text-[#4d5156] dark:text-[#bdc1c6] line-clamp-2">
                {{ $displayDesc }}
            </p>
        </div>

        <!-- Mobile Container -->
        <div x-show="mode === 'mobile'" class="max-w-[375px] mx-auto p-3 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-950 font-sans shadow-inner">
            <!-- Mobile Header -->
            <div class="flex items-center space-x-2 text-xs mb-2">
                <div class="w-7 h-7 rounded-full bg-white dark:bg-gray-800 flex items-center justify-center text-gray-500 overflow-hidden shrink-0 shadow-sm border border-gray-200 dark:border-gray-700">
                    <img src="{{ $url }}/favicon.ico" alt="Favicon" class="w-4 h-4" onerror="this.style.display='none'" />
                </div>
                <div class="leading-tight truncate">
                    <span class="font-medium text-gray-900 dark:text-gray-100 text-xs">{{ $name }}</span>
                    <span class="text-gray-500 dark:text-gray-400 block truncate text-[10px]">{{ $url }}</span>
                </div>
            </div>

            <!-- Mobile Title -->
            <h3 class="text-[18px] leading-snug font-medium text-[#1a0dab] dark:text-[#8ab4f8] mb-1">
                {{ $displayTitle }}
            </h3>

            <!-- Mobile Description -->
            <p class="text-[13px] leading-relaxed text-[#4d5156] dark:text-[#bdc1c6] line-clamp-3">
                {{ $displayDesc }}
            </p>
        </div>
    </div>

    <!-- Character & Optimization Meters -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-gray-100 dark:border-gray-800 text-xs">
        <div>
            <div class="flex justify-between items-center mb-1">
                <span class="text-gray-600 dark:text-gray-400">Longitud del Título:</span>
                <span class="font-medium {{ $titleStatus }}">{{ $titleLen }} / 60 car.</span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 h-1.5 rounded-full overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-300 {{ $titleLen <= 60 ? 'bg-success-500' : 'bg-amber-500' }}"
                    style="width: {{ min(100, ($titleLen / 60) * 100) }}%"
                ></div>
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5">Google muestra hasta ~60 caracteres en resultados de escritorio.</p>
        </div>

        <div>
            <div class="flex justify-between items-center mb-1">
                <span class="text-gray-600 dark:text-gray-400">Longitud de Descripción:</span>
                <span class="font-medium {{ $descStatus }}">{{ $descLen }} / 160 car.</span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 h-1.5 rounded-full overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-300 {{ ($descLen >= 120 && $descLen <= 160) ? 'bg-success-500' : ($descLen < 120 ? 'bg-blue-500' : 'bg-amber-500') }}"
                    style="width: {{ min(100, ($descLen / 160) * 100) }}%"
                ></div>
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5">Recomendado entre 120 y 160 caracteres para máxima visibilidad.</p>
        </div>
    </div>
</div>
