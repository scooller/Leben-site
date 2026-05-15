<div class="space-y-4">
    <div class="flex justify-center rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
        {!! $qrSvg !!}
    </div>

    <div class="flex justify-center">
        <a
            href="{{ $qrDownloadUrl ?? '#' }}"
            download="{{ $qrDownloadName ?? 'qr.svg' }}"
            class="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
        >
            Descargar QR
        </a>
    </div>

    <div class="space-y-2">
        <p class="text-sm font-medium text-gray-950 dark:text-white">URL</p>
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm break-all text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
            {{ $url }}
        </div>
    </div>
</div>
