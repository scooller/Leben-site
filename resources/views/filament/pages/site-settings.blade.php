<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 1.5rem;">
            <x-filament::button type="submit">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="size-6">
				<path strokeLinecap="round" strokeLinejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
				<path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
			</svg> Guardar Configuración
            </x-filament::button>
        </div>
    </form>

    @script
    <script>
        const computeHash = () => {
            if (!$wire || !$wire.data) return ''
            try {
                if (typeof window.jsMd5 === 'function') {
                    return window.jsMd5(JSON.stringify($wire.data).replace(/\\/g, ''))
                }
                return JSON.stringify($wire.data)
            } catch (e) {
                return ''
            }
        }

        let cleanHash = computeHash()
        let userHasModifiedForm = false

        const formEl = document.querySelector('form[wire\\:submit="save"]') || document.querySelector('form')
        if (formEl) {
            const markModified = () => { userHasModifiedForm = true }
            formEl.addEventListener('input', markModified, { passive: true })
            formEl.addEventListener('change', markModified, { passive: true })
            formEl.addEventListener('click', (e) => {
                if (e.target.closest('button:not([type="submit"]), [role="button"], input, select, textarea')) {
                    userHasModifiedForm = true
                }
            }, { passive: true })
        }

        setTimeout(() => {
            if (!userHasModifiedForm) {
                cleanHash = computeHash()
            }
        }, 500)

        $wire.on('site-settings-saved', () => {
            userHasModifiedForm = false
            requestAnimationFrame(() => {
                cleanHash = computeHash()
            })
        })

        const isDirty = () => {
            if (!cleanHash || !$wire || !$wire.data) return false
            return userHasModifiedForm && (computeHash() !== cleanHash)
        }

        const alertMessage = @js(__('filament-panels::unsaved-changes-alert.body'))

        const handleNavigate = (event) => {
            if (!isDirty()) return
            if (confirm(alertMessage)) {
                userHasModifiedForm = false
                cleanHash = computeHash()
                return
            }
            event.preventDefault()
        }

        const handleBeforeUnload = (event) => {
            if (!isDirty()) return
            event.preventDefault()
            event.returnValue = true
        }

        document.addEventListener('livewire:navigate', handleNavigate)
        window.addEventListener('beforeunload', handleBeforeUnload)

        document.addEventListener('livewire:navigating', () => {
            document.removeEventListener('livewire:navigate', handleNavigate)
            window.removeEventListener('beforeunload', handleBeforeUnload)
        }, { once: true })
    </script>
    @endscript
</x-filament-panels::page>
