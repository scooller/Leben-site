<x-filament-panels::page>
    <div wire:poll.2s="refreshProgress" style="display: grid; gap: 1rem;">
        @if (($this->snapshot['status'] ?? 'not_found') === 'not_found')
            <div style="padding: 1rem; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: .75rem;">
                No se encontró una sincronización activa para monitorear. Inicia el proceso desde Configuración del Sitio.
            </div>
        @else
            <div style="padding: 1rem; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: .75rem; display: grid; gap: .75rem;">
                <div style="display:flex; justify-content:space-between; gap:.75rem; flex-wrap:wrap;">
                    <strong>Estado: {{ strtoupper((string) ($this->snapshot['status'] ?? 'running')) }}</strong>
                    <span>Origen: {{ (string) ($this->snapshot['base_url'] ?? '-') }}</span>
                </div>

                <div style="display:grid; gap:.35rem;">
                    <div style="height: 10px; border-radius: 999px; background: rgba(255,255,255,.12); overflow: hidden;">
                        <div style="height: 100%; background: #eb0029; width: {{ (int) ($this->snapshot['progress_percent'] ?? 0) }}%; transition: width .25s ease;"></div>
                    </div>
                    <div style="font-size: .9rem; opacity: .9;">
                        Progreso: {{ (int) ($this->snapshot['progress_percent'] ?? 0) }}% ({{ (int) ($this->snapshot['processed'] ?? 0) }} / {{ (int) ($this->snapshot['total_steps'] ?? 0) }})
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:.5rem; font-size:.9rem;">
                    <div>Creados: <strong>{{ (int) ($this->snapshot['created'] ?? 0) }}</strong></div>
                    <div>Actualizados: <strong>{{ (int) ($this->snapshot['updated'] ?? 0) }}</strong></div>
                    <div>Omitidos: <strong>{{ (int) ($this->snapshot['skipped'] ?? 0) }}</strong></div>
                    <div>Fallidos: <strong>{{ (int) ($this->snapshot['failed'] ?? 0) }}</strong></div>
                </div>

                @if (filled($this->snapshot['error'] ?? null))
                    <div style="padding: .65rem .75rem; border-radius: .5rem; background: rgba(235, 0, 41, .15); border: 1px solid rgba(235, 0, 41, .45);">
                        Error: {{ (string) $this->snapshot['error'] }}
                    </div>
                @endif
            </div>

            <div style="padding: 1rem; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: .75rem; display:grid; gap:.5rem;">
                <strong>Log en vivo</strong>
                <pre style="margin:0; max-height: 380px; overflow:auto; white-space: pre-wrap; font-size:.84rem; line-height:1.35;">{{ implode("\n", (array) ($this->snapshot['logs'] ?? [])) }}</pre>
            </div>
        @endif
    </div>
</x-filament-panels::page>
