/**
 * SplitWise PRO - PWA Registration & Install Experience
 */

(function () {
    let deferredPrompt = null;

    // 1. Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js', { scope: './' })
                .then((registration) => {
                    // Check for updates
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        if (newWorker) {
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    console.log('[PWA] New version available.');
                                }
                            });
                        }
                    });
                })
                .catch((err) => {
                    console.warn('[PWA] Service Worker registration failed:', err);
                });
        });
    }

    // 2. Handle Add to Home Screen (beforeinstallprompt)
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent default browser banner
        e.preventDefault();
        deferredPrompt = e;

        // Show install button in navigation bar
        const navInstallBtn = document.getElementById('pwa-install-btn');
        if (navInstallBtn) {
            navInstallBtn.style.display = 'inline-flex';
        }

        // Show bottom install banner if present
        const installBanner = document.getElementById('pwa-install-banner');
        if (installBanner && !sessionStorage.getItem('pwa_banner_dismissed')) {
            installBanner.style.display = 'flex';
        }
    });

    // 3. Global Install trigger function
    window.installPWA = async function () {
        if (!deferredPrompt) {
            alert('To install SplitWise PRO:\n• On Chrome/Android: Tap menu (⋮) > "Install app" or "Add to Home screen"\n• On Safari/iOS: Tap Share button > "Add to Home Screen"');
            return;
        }

        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        console.log(`[PWA] Install prompt outcome: ${outcome}`);

        deferredPrompt = null;
        const navInstallBtn = document.getElementById('pwa-install-btn');
        if (navInstallBtn) navInstallBtn.style.display = 'none';
        const installBanner = document.getElementById('pwa-install-banner');
        if (installBanner) installBanner.style.display = 'none';
    };

    window.dismissPWABanner = function () {
        sessionStorage.setItem('pwa_banner_dismissed', '1');
        const installBanner = document.getElementById('pwa-install-banner');
        if (installBanner) installBanner.style.display = 'none';
    };

    window.addEventListener('appinstalled', () => {
        console.log('[PWA] SplitWise PRO successfully installed!');
        deferredPrompt = null;
        const navInstallBtn = document.getElementById('pwa-install-btn');
        if (navInstallBtn) navInstallBtn.style.display = 'none';
        const installBanner = document.getElementById('pwa-install-banner');
        if (installBanner) installBanner.style.display = 'none';
    });
})();
