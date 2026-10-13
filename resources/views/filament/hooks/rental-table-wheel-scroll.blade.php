<script data-navigate-once>
    if (! window.__cremonaRentalWheelScroll) {
        window.__cremonaRentalWheelScroll = true;

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
    }
</script>
