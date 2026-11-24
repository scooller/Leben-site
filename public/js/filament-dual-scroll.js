(function () {
    'use strict';

    function registerDualScroll() {
        if (!window.Alpine || window.__filamentDualScrollAlpineRegistered) {
            return;
        }
        window.__filamentDualScrollAlpineRegistered = true;

        window.Alpine.data('filamentDualScroll', () => ({
            init() {
                const mirror = this.$el;
                const inner = this.$refs.inner;
                const ctn = mirror.closest('.fi-ta-ctn') || mirror.parentElement;
                const container = ctn?.querySelector('.fi-ta-content-ctn');

                if (!container || !inner) {
                    return;
                }

                const sync = () => {
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
                };

                sync();
                requestAnimationFrame(sync);
                setTimeout(sync, 100);

                const ro = new ResizeObserver(sync);
                ro.observe(container);

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

                if (window.Livewire) {
                    window.Livewire.hook('commit', ({ succeed }) => {
                        succeed(() => {
                            requestAnimationFrame(sync);
                            setTimeout(sync, 50);
                        });
                    });
                    window.Livewire.hook('morph.updated', () => {
                        requestAnimationFrame(sync);
                    });
                }

                document.addEventListener('resized-column:table-scrolled', sync);
                document.addEventListener('resized-column:sticky-refreshed', sync);
            }
        }));
    }

    if (window.Alpine) {
        registerDualScroll();
    } else {
        document.addEventListener('alpine:init', registerDualScroll);
    }
})();
