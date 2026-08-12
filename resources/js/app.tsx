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
    const isStandaloneGamePage = path.startsWith('/game/');

    if (!isStandaloneGamePage) {
        return null;
    }

    return (
        <button
            className="game-home-nav-button"
            type="button"
            onClick={() => router.visit('/main', {
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

function WorldMapControls() {
    useEffect(() => {
        type MapOffset = { x: number; y: number };
        type DragState = {
            pointerId: number;
            viewport: HTMLElement;
            canvas: HTMLElement;
            startX: number;
            startY: number;
            offsetX: number;
            offsetY: number;
        };

        let drag: DragState | null = null;
        let activeViewport: HTMLElement | null = null;
        let activeCurrentNode: HTMLElement | null = null;
        let syncFrame = 0;

        const getCanvas = (viewport: HTMLElement) =>
            viewport.querySelector<HTMLElement>('.world-map-canvas');

        const getScale = (canvas: HTMLElement) => {
            const value = Number.parseFloat(
                getComputedStyle(canvas).getPropertyValue('--world-map-scale'),
            );

            return Number.isFinite(value) && value > 0 ? value : 0.57;
        };

        const getOffset = (canvas: HTMLElement): MapOffset => ({
            x: Number.parseFloat(canvas.dataset.mapOffsetX ?? '0') || 0,
            y: Number.parseFloat(canvas.dataset.mapOffsetY ?? '0') || 0,
        });

        const clampOffset = (
            viewport: HTMLElement,
            canvas: HTMLElement,
            offset: MapOffset,
        ): MapOffset => {
            const nodes = Array.from(
                canvas.querySelectorAll<HTMLElement>('.world-map-node'),
            );

            if (nodes.length === 0) {
                return offset;
            }

            const scale = getScale(canvas);
            const guard = Math.min(44, viewport.clientWidth * 0.14, viewport.clientHeight * 0.18);
            const left = guard;
            const right = viewport.clientWidth - guard;
            const top = guard;
            const bottom = viewport.clientHeight - guard;
            let nearestCorrection: MapOffset | null = null;
            let nearestDistance = Number.POSITIVE_INFINITY;

            for (const node of nodes) {
                const x = offset.x + (node.offsetLeft * scale);
                const y = offset.y + (node.offsetTop * scale);
                const visibleX = Math.min(right, Math.max(left, x));
                const visibleY = Math.min(bottom, Math.max(top, y));
                const correctionX = visibleX - x;
                const correctionY = visibleY - y;
                const distance = (correctionX ** 2) + (correctionY ** 2);

                if (distance === 0) {
                    return offset;
                }

                if (distance < nearestDistance) {
                    nearestDistance = distance;
                    nearestCorrection = { x: correctionX, y: correctionY };
                }
            }

            return nearestCorrection
                ? {
                    x: offset.x + nearestCorrection.x,
                    y: offset.y + nearestCorrection.y,
                }
                : offset;
        };

        const applyOffset = (canvas: HTMLElement, offset: MapOffset) => {
            canvas.dataset.mapOffsetX = String(offset.x);
            canvas.dataset.mapOffsetY = String(offset.y);
            canvas.style.setProperty('--map-offset-x', `${offset.x}px`);
            canvas.style.setProperty('--map-offset-y', `${offset.y}px`);
        };

        const centerOnCurrentLocation = (viewport: HTMLElement, force = false) => {
            const canvas = getCanvas(viewport);
            const currentNode = canvas?.querySelector<HTMLElement>('.world-map-node.current') ?? null;

            if (!canvas || !currentNode) {
                return;
            }

            if (!force && activeViewport === viewport && activeCurrentNode === currentNode) {
                return;
            }

            activeViewport = viewport;
            activeCurrentNode = currentNode;

            const scale = getScale(canvas);
            const centered = {
                x: (viewport.clientWidth / 2) - (currentNode.offsetLeft * scale),
                y: (viewport.clientHeight / 2) - (currentNode.offsetTop * scale),
            };

            applyOffset(canvas, clampOffset(viewport, canvas, centered));
        };

        const syncMap = () => {
            syncFrame = 0;
            const viewport = document.querySelector<HTMLElement>('.world-map-viewport');

            if (!viewport) {
                activeViewport = null;
                activeCurrentNode = null;
                return;
            }

            centerOnCurrentLocation(viewport);
        };

        const scheduleSync = () => {
            if (syncFrame) {
                return;
            }

            syncFrame = window.requestAnimationFrame(syncMap);
        };

        const onPointerDown = (event: PointerEvent) => {
            const target = event.target instanceof Element ? event.target : null;
            const viewport = target?.closest<HTMLElement>('.world-map-viewport') ?? null;

            if (!viewport || target?.closest('.world-map-node')) {
                return;
            }

            const canvas = getCanvas(viewport);
            if (!canvas) {
                return;
            }

            const offset = getOffset(canvas);
            drag = {
                pointerId: event.pointerId,
                viewport,
                canvas,
                startX: event.clientX,
                startY: event.clientY,
                offsetX: offset.x,
                offsetY: offset.y,
            };

            viewport.setPointerCapture?.(event.pointerId);
            event.preventDefault();
            event.stopPropagation();
        };

        const onPointerMove = (event: PointerEvent) => {
            if (!drag || drag.pointerId !== event.pointerId) {
                return;
            }

            const next = clampOffset(drag.viewport, drag.canvas, {
                x: drag.offsetX + event.clientX - drag.startX,
                y: drag.offsetY + event.clientY - drag.startY,
            });

            applyOffset(drag.canvas, next);
            event.preventDefault();
            event.stopPropagation();
        };

        const finishDrag = (event: PointerEvent) => {
            if (!drag || drag.pointerId !== event.pointerId) {
                return;
            }

            if (drag.viewport.hasPointerCapture?.(event.pointerId)) {
                drag.viewport.releasePointerCapture(event.pointerId);
            }

            drag = null;
            event.preventDefault();
            event.stopPropagation();
        };

        const onResize = () => {
            if (activeViewport?.isConnected) {
                centerOnCurrentLocation(activeViewport, true);
            } else {
                scheduleSync();
            }
        };

        const observer = new MutationObserver(scheduleSync);
        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class'],
        });

        window.addEventListener('pointerdown', onPointerDown, { capture: true, passive: false });
        window.addEventListener('pointermove', onPointerMove, { capture: true, passive: false });
        window.addEventListener('pointerup', finishDrag, { capture: true, passive: false });
        window.addEventListener('pointercancel', finishDrag, { capture: true, passive: false });
        window.addEventListener('resize', onResize);
        scheduleSync();

        return () => {
            observer.disconnect();
            if (syncFrame) {
                window.cancelAnimationFrame(syncFrame);
            }
            window.removeEventListener('pointerdown', onPointerDown, true);
            window.removeEventListener('pointermove', onPointerMove, true);
            window.removeEventListener('pointerup', finishDrag, true);
            window.removeEventListener('pointercancel', finishDrag, true);
            window.removeEventListener('resize', onResize);
        };
    }, []);

    return null;
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
                <WorldMapControls />
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
