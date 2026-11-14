<script data-navigate-once>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '{{ config('cremona.pwa.scope') }}' }));
    }
</script>
