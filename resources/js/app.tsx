import '../css/auth.css';
import '../css/game-map.css';
import '../css/game-systems.css';
import '../css/game-home-nav.css';
import '../css/game-location-title.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { Map } from 'lucide-react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

function GameHomeNavButton() {
    if (typeof window === 'undefined') {
        return null;
    }

    const path = window.location.pathname;
    const isGamePage = path === '/dashboard' || path.startsWith('/game/');

    if (!isGamePage) {
        return null;
    }

    return (
        <button
            className="game-home-nav-button"
            type="button"
            onClick={() => router.visit('/dashboard', {
                preserveState: false,
                preserveScroll: false,
            })}
            aria-label="Grįžti į pagrindinį žemėlapį"
        >
            <Map size={20} />
            <span>World</span>
        </button>
    );
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'game':
            case name === 'location-category':
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
                <GameHomeNavButton />
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
