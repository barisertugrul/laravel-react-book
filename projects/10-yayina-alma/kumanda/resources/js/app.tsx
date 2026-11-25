import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { configureEcho } from '@laravel/echo-react';

// Canlı yayın sürücüsü .env'den gelir: reverb (kendi sunucunuz) ya da pusher (barındırılan servis:
// Pusher, Ably'nin Pusher uyumlu ucu). "null"/"log" ise Echo kurulmaz; ilerleme sayfası yoklama yapar.
const broadcaster = import.meta.env.VITE_BROADCAST_CONNECTION;

if (broadcaster === 'reverb' || broadcaster === 'pusher') {
    configureEcho({ broadcaster });
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'fake-billing': // sahte sağlayıcının sayfası: panelin düzeni yok
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
