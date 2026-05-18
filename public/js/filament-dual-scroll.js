(function () {
    'use strict';

    function setupDualScroll(container) {
        if (container.dataset.dualScroll === '1') {
            return;
        }

        container.dataset.dualScroll = '1';

        const mirror = document.createElement('div');
        mirror.style.cssText = 'overflow-x: auto; overflow-y: hidden; height: 14px;';
        mirror.setAttribute('aria-hidden', 'true');

        const inner = document.createElement('div');
        inner.style.height = '1px';
        mirror.appendChild(inner);

        container.parentNode.insertBefore(mirror, container);

        function syncWidth() {
            inner.style.width = container.scrollWidth + 'px';
        }

        syncWidth();

        const resizeObserver = new ResizeObserver(syncWidth);
        resizeObserver.observe(container);

        let syncing = false;

        mirror.addEventListener('scroll', () => {
            if (!syncing) {
                syncing = true;
                container.scrollLeft = mirror.scrollLeft;
                syncing = false;
            }
        });

        container.addEventListener('scroll', () => {
            if (!syncing) {
                syncing = true;
                mirror.scrollLeft = container.scrollLeft;
                syncing = false;
            }
        });
    }

    function init() {
        document.querySelectorAll('.fi-ta-content-ctn').forEach(setupDualScroll);
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('livewire:navigated', init);
    document.addEventListener('livewire:update', () => setTimeout(init, 50));
})();
