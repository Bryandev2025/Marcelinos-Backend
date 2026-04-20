<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(function (error) {
                console.warn('Service worker registration failed:', error);
            });
        });
    }
</script>
