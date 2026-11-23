(function () {
    'use strict';

    function setupDualScroll(container) {
        if (!container || !container.isConnected) {
            return;
        }

        let mirror = container.previousElementSibling;
        const hasMirror = mirror && mirror.classList && mirror.classList.contains('fi-ta-dual-scroll');

        if (hasMirror) {
            if (typeof mirror._syncDimensions === 'function') {
                mirror._syncDimensions();
            }
            return;
        }

        mirror = document.createElement('div');
        mirror.className = 'fi-ta-dual-scroll';
        mirror.setAttribute('wire:ignore', '');
        mirror.setAttribute('aria-hidden', 'true');
        mirror.style.cssText = 'overflow-x: auto; overflow-y: hidden; height: 16px; width: 100%; display: none;';

        const inner = document.createElement('div');
        inner.style.height = '1px';
        mirror.appendChild(inner);

        container.parentNode.insertBefore(mirror, container);

        function syncDimensions() {
            if (!container.isConnected || !mirror.isConnected) {
                return;
            }

            const scrollWidth = container.scrollWidth;
            const clientWidth = container.clientWidth;

            if (scrollWidth > clientWidth) {
                mirror.style.display = 'block';
                inner.style.width = scrollWidth + 'px';
                if (Math.abs(mirror.scrollLeft - container.scrollLeft) > 1) {
                    mirror.scrollLeft = container.scrollLeft;
                }
            } else {
                mirror.style.display = 'none';
            }
        }

        mirror._syncDimensions = syncDimensions;
        syncDimensions();

        const resizeObserver = new ResizeObserver(syncDimensions);
        resizeObserver.observe(container);

        let isSyncing = false;

        mirror.addEventListener('scroll', () => {
            if (!isSyncing) {
                isSyncing = true;
                container.scrollLeft = mirror.scrollLeft;
                isSyncing = false;
            }
        }, { passive: true });

        container.addEventListener('scroll', () => {
            if (!isSyncing) {
                isSyncing = true;
                mirror.scrollLeft = container.scrollLeft;
                isSyncing = false;
            }
        }, { passive: true });
    }

    let initScheduled = false;

    function init() {
        if (initScheduled) {
            return;
        }
        initScheduled = true;
        requestAnimationFrame(() => {
            initScheduled = false;
            document.querySelectorAll('.fi-ta-content-ctn').forEach(setupDualScroll);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);

    function bindLivewire() {
        if (!window.Livewire) {
            return;
        }

        try {
            window.Livewire.hook('commit', ({ succeed }) => {
                succeed(() => {
                    init();
                    setTimeout(init, 50);
                });
            });
        } catch (e) {}

        try {
            window.Livewire.hook('morph.updated', () => {
                init();
            });
        } catch (e) {}
    }

    if (window.Livewire) {
        bindLivewire();
    } else {
        document.addEventListener('livewire:init', bindLivewire);
        document.addEventListener('livewire:initialized', bindLivewire);
    }

    document.addEventListener('resized-column:table-scrolled', init);
    document.addEventListener('resized-column:sticky-refreshed', init);

    const mutationObserver = new MutationObserver(() => {
        init();
    });

    function startObserving() {
        const main = document.querySelector('.fi-main') || document.body;
        if (main) {
            mutationObserver.observe(main, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startObserving);
    } else {
        startObserving();
    }
})();
