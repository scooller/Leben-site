<x-filament-panels::page>
    <div wire:poll.2s="refreshProgress" style="display: grid; gap: 1rem;">
        @if (($this->snapshot['status'] ?? 'not_found') === 'not_found')
            <div style="padding: 1rem; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: .75rem;">
                No se encontró una importación activa para monitorear. Inicia una importación y abre esta vista desde el enlace de la notificación.
            </div>
        @else
            <div style="padding: 1rem; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: .75rem; display: grid; gap: .75rem;">
                <div style="display:flex; justify-content:space-between; gap:.75rem; flex-wrap:wrap;">
                    <strong>Estado: {{ strtoupper((string) ($this->snapshot['status'] ?? 'running')) }}</strong>
                    <span>Canal: {{ (string) ($this->snapshot['channel_name'] ?? '-') }}</span>
                    <span>Modo: {{ (bool) ($this->snapshot['dry_run'] ?? false) ? 'Dry-run' : 'Importación real' }}</span>
                    <span>Salesforce: {{ (bool) ($this->snapshot['sync_to_salesforce'] ?? false) ? 'Sí' : 'No' }}</span>
                </div>

                <div style="display:grid; gap:.35rem;">
                    <div style="height: 10px; border-radius: 999px; background: rgba(255,255,255,.12); overflow: hidden;">
                        <div style="height: 100%; background: #eb0029; width: {{ (int) ($this->snapshot['progress_percent'] ?? 0) }}%; transition: width .25s ease;"></div>
                    </div>
                    <div style="font-size: .9rem; opacity: .9;">
                        Progreso: {{ (int) ($this->snapshot['progress_percent'] ?? 0) }}% ({{ (int) ($this->snapshot['processed'] ?? 0) }} / {{ (int) ($this->snapshot['total_rows'] ?? 0) }})
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:.5rem; font-size:.9rem;">
                    <div>{{ (bool) ($this->snapshot['dry_run'] ?? false) ? 'Válidos' : 'Creados' }}: <strong>{{ (int) ($this->snapshot['created'] ?? 0) }}</strong></div>
                    <div>Fallidos: <strong>{{ (int) ($this->snapshot['failed'] ?? 0) }}</strong></div>
                    <div>Warnings: <strong>{{ (int) ($this->snapshot['warnings'] ?? 0) }}</strong></div>
                    <div>Sync OK: <strong>{{ (int) ($this->snapshot['synced'] ?? 0) }}</strong></div>
                    <div>Sync Error: <strong>{{ (int) ($this->snapshot['sync_failed'] ?? 0) }}</strong></div>
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
