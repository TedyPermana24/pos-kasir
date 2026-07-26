<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<!-- PWA Manifest & Meta Tags -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#18181b">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="POS Kasir">

<script>
    let deferredPwaPrompt = null;

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
                .then(reg => console.log('Service Worker registered:', reg.scope))
                .catch(err => console.error('Service Worker registration failed:', err));
        });
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPwaPrompt = e;
        const btn = document.getElementById('pwa-install-banner');
        if (btn) btn.classList.remove('hidden');
    });

    window.installPwaApp = function () {
        if (deferredPwaPrompt) {
            deferredPwaPrompt.prompt();
            deferredPwaPrompt.userChoice.then((choice) => {
                if (choice.outcome === 'accepted') {
                    console.log('PWA Install accepted');
                }
                deferredPwaPrompt = null;
                const btn = document.getElementById('pwa-install-banner');
                if (btn) btn.classList.add('hidden');
            });
        } else {
            alert('Untuk menginstall POS Kasir:\n1. Pastikan Anda mengakses via HTTPS / localhost.\n2. Buka menu browser (titik 3 di kanan atas).\n3. Pilih "Install aplikasi" / "Tambahkan ke Layar Utama".');
        }
    };
</script>

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

