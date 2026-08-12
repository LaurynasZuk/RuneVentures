import '../css/auth.css';
import '../css/game-map.css';
import '../css/game-systems.css';
import '../css/game-home-nav.css';
import '../css/game-location-title.css';
import '../css/game-player-bar.css';
import '../css/combat.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { Map } from 'lucide-react';
import { useEffect } from 'react';
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

function LiveGameTimers() {
    useEffect(() => {
        const deadlines = new WeakMap<HTMLElement, number>();
        const templates = new WeakMap<HTMLElement, string>();

        const tick = () => {
            document.querySelectorAll<HTMLElement>('.game-toast').forEach((element) => {
                if (element.style.display === 'none') {
                    return;
                }

                if (!deadlines.has(element)) {
                    const text = element.textContent ?? '';
                    const match = text.match(/(\d+)\s*s\b/i);

                    if (!match) {
                        return;
                    }

                    deadlines.set(element, Date.now() + Number(match[1]) * 1000);
                    templates.set(element, text);
                }

                const deadline = deadlines.get(element);
                const template = templates.get(element);

                if (!deadline || !template) {
                    return;
                }

                const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));

                if (remaining <= 0) {
                    element.style.display = 'none';
                    return;
                }

                element.textContent = template.replace(/\d+\s*s\b/i, `${remaining} s`);
            });
        };

        tick();
        const timer = window.setInterval(tick, 250);

        const syncOnFocus = () => tick();
        window.addEventListener('focus', syncOnFocus);
        document.addEventListener('visibilitychange', syncOnFocus);

        return () => {
            window.clearInterval(timer);
            window.removeEventListener('focus', syncOnFocus);
            document.removeEventListener('visibilitychange', syncOnFocus);
        };
    }, []);

    return null;
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'game':
            case name === 'combat':
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
                <LiveGameTimers />
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
