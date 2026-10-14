<script data-navigate-once>
    if (! window.__cremonaRentalWheelScroll) {
        window.__cremonaRentalWheelScroll = true;

        const mountRentalScrollbar = () => {
            document.querySelectorAll('.rental-table').forEach((table) => {
                const container = table.querySelector('.fi-ta-content-ctn');

                if (! container) {
                    return;
                }

                let control = table.querySelector('[data-rental-scroll-control]');

                if (! control) {
                    control = document.createElement('div');
                    control.dataset.rentalScrollControl = 'true';
                    control.className = 'rental-table-scroll-control';
                    control.innerHTML = `
                        <span>Défilement horizontal</span>
                        <input type="range" min="0" value="0" step="1" aria-label="Faire défiler les colonnes de locations horizontalement">
                    `;
                    container.after(control);
                }

                const slider = control.querySelector('input');
                const synchronize = () => {
                    const maximum = Math.max(0, Math.ceil(container.scrollWidth - container.clientWidth));

                    slider.disabled = maximum === 0;
                    slider.max = String(Math.max(1, maximum));
                    slider.value = String(Math.min(maximum, Math.round(container.scrollLeft)));
                };

                if (! control.dataset.rentalScrollBound) {
                    control.dataset.rentalScrollBound = 'true';
                    slider.addEventListener('input', () => {
                        container.scrollLeft = Number(slider.value);
                    });
                    container.addEventListener('scroll', synchronize, { passive: true });
                    window.addEventListener('resize', synchronize, { passive: true });
                }

                synchronize();
            });
        };

        document.addEventListener('wheel', (event) => {
            if (event.ctrlKey || Math.abs(event.deltaX) > Math.abs(event.deltaY)) {
                return;
            }

            const table = event.target.closest('.rental-table');
            const container = table?.querySelector('.fi-ta-content-ctn');

            if (! container) {
                return;
            }

            const maximum = container.scrollWidth - container.clientWidth;
            const nextPosition = Math.max(0, Math.min(maximum, container.scrollLeft + event.deltaY));

            if (maximum <= 0 || nextPosition === container.scrollLeft) {
                return;
            }

            container.scrollLeft = nextPosition;
            event.preventDefault();
        }, { passive: false });

        document.addEventListener('livewire:navigated', mountRentalScrollbar);
        document.addEventListener('DOMContentLoaded', mountRentalScrollbar);
        new MutationObserver(mountRentalScrollbar).observe(document.body, { childList: true, subtree: true });
        requestAnimationFrame(mountRentalScrollbar);
    }
</script>
