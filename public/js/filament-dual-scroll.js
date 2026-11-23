(function () {
    'use strict';

    function setupDualScroll(container) {
        if (!container || container.dataset.dualScroll === '1') {
            return;
        }

        container.dataset.dualScroll = '1';

        const mirror = document.createElement('div');
        mirror.className = 'fi-ta-dual-scroll';
        mirror.style.cssText = 'overflow-x: auto; overflow-y: hidden; height: 16px; width: 100%; display: none;';
        mirror.setAttribute('aria-hidden', 'true');

        const inner = document.createElement('div');
        inner.style.height = '1px';
        mirror.appendChild(inner);

        container.parentNode.insertBefore(mirror, container);

        function syncDimensions() {
            const scrollWidth = container.scrollWidth;
            const clientWidth = container.clientWidth;

            if (scrollWidth > clientWidth) {
                mirror.style.display = 'block';
                inner.style.width = scrollWidth + 'px';
                mirror.scrollLeft = container.scrollLeft;
            } else {
                mirror.style.display = 'none';
            }
        }

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

    function init() {
        document.querySelectorAll('.fi-ta-content-ctn').forEach(setupDualScroll);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);

    if (window.Livewire) {
        window.Livewire.hook('commit', () => requestAnimationFrame(init));
        window.Livewire.hook('morph.updated', () => requestAnimationFrame(init));
    } else {
        document.addEventListener('livewire:init', () => {
            window.Livewire.hook('commit', () => requestAnimationFrame(init));
            window.Livewire.hook('morph.updated', () => requestAnimationFrame(init));
        });
    }
})();
