import { Head, router } from '@inertiajs/react';
import { Shield, Swords } from 'lucide-react';
import { useEffect, useState } from 'react';

type CombatState = {
    status: 'active' | 'won' | 'lost' | 'fled';
    tickMs: number;
    style: 'melee' | 'ranged' | 'magic';
    player: {
        hp: number;
        maxHp: number;
        attackTicks: number;
        attackSeconds: number;
        maxHit: number;
        nextAttackAt: string | null;
    };
    monster: {
        slug: string;
        name: string;
        level: number;
        hp: number;
        maxHp: number;
        attackTicks: number;
        attackSeconds: number;
        maxHit: number;
        nextAttackAt: string | null;
    };
    lastEvent: string | null;
};

interface Props {
    combat: CombatState | null;
}

export default function Combat({ combat: initialCombat }: Props) {
    const [combat, setCombat] = useState(initialCombat);
    const [now, setNow] = useState(Date.now());

    useEffect(() => {
        const clock = window.setInterval(() => setNow(Date.now()), 100);
        return () => window.clearInterval(clock);
    }, []);

    useEffect(() => {
        if (!combat || combat.status !== 'active') return;

        let cancelled = false;

        const refresh = async () => {
            const response = await fetch('/game/combat/state', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok || cancelled) return;

            const data = await response.json();
            if (!cancelled) setCombat(data.combat);
        };

        const poller = window.setInterval(refresh, 350);
        refresh();

        return () => {
            cancelled = true;
            window.clearInterval(poller);
        };
    }, [combat?.status]);

    const remaining = (endsAt: string | null) => {
        if (!endsAt) return 0;
        return Math.max(0, new Date(endsAt).getTime() - now);
    };

    if (!combat) {
        return (
            <div className="game-shell combat-page">
                <Head title="Combat · RuneVentures" />
                <main className="combat-empty">
                    <Swords size={30} />
                    <h1>Nėra aktyvios kovos</h1>
                    <button type="button" onClick={() => router.visit('/dashboard')}>Grįžti į World</button>
                </main>
            </div>
        );
    }

    const playerRemaining = remaining(combat.player.nextAttackAt);
    const monsterRemaining = remaining(combat.monster.nextAttackAt);
    const playerCycle = combat.player.attackTicks * combat.tickMs;
    const monsterCycle = combat.monster.attackTicks * combat.tickMs;
    const playerProgress = playerCycle > 0 ? Math.max(0, Math.min(100, 100 - (playerRemaining / playerCycle) * 100)) : 100;
    const monsterProgress = monsterCycle > 0 ? Math.max(0, Math.min(100, 100 - (monsterRemaining / monsterCycle) * 100)) : 100;

    return (
        <div className="game-shell combat-page">
            <Head title={`${combat.monster.name} · Combat · RuneVentures`} />

            <main>
                <section className="combat-arena">
                    <div className="combat-fighter player">
                        <span className="combat-fighter-label">Tu</span>
                        <strong>{combat.style.toUpperCase()}</strong>
                        <div className="combat-hp-line">
                            <span>HP</span>
                            <b>{combat.player.hp}/{combat.player.maxHp}</b>
                        </div>
                        <div className="combat-hp-bar"><i style={{ width: `${(combat.player.hp / Math.max(1, combat.player.maxHp)) * 100}%` }} /></div>
                    </div>

                    <div className="combat-versus"><Swords size={24} /></div>

                    <div className="combat-fighter monster">
                        <span className="combat-fighter-label">Monster</span>
                        <strong>{combat.monster.name}</strong>
                        <small>Combat {combat.monster.level}</small>
                        <div className="combat-hp-line">
                            <span>HP</span>
                            <b>{combat.monster.hp}/{combat.monster.maxHp}</b>
                        </div>
                        <div className="combat-hp-bar"><i style={{ width: `${(combat.monster.hp / Math.max(1, combat.monster.maxHp)) * 100}%` }} /></div>
                    </div>
                </section>

                {combat.status === 'active' && (
                    <section className="combat-timers">
                        <div className="combat-timer-card">
                            <div className="combat-timer-heading">
                                <Swords size={18} />
                                <div>
                                    <span>Tavo smūgis</span>
                                    <strong>{(playerRemaining / 1000).toFixed(1)} s</strong>
                                </div>
                                <small>{combat.player.attackTicks} ticks · {combat.player.attackSeconds.toFixed(1)} s</small>
                            </div>
                            <div className="combat-timer-bar"><i style={{ width: `${playerProgress}%` }} /></div>
                            <p>Max hit {combat.player.maxHit}</p>
                        </div>

                        <div className="combat-timer-card monster-timer">
                            <div className="combat-timer-heading">
                                <Shield size={18} />
                                <div>
                                    <span>Monstro smūgis</span>
                                    <strong>{(monsterRemaining / 1000).toFixed(1)} s</strong>
                                </div>
                                <small>{combat.monster.attackTicks} ticks · {combat.monster.attackSeconds.toFixed(1)} s</small>
                            </div>
                            <div className="combat-timer-bar"><i style={{ width: `${monsterProgress}%` }} /></div>
                            <p>Max hit {combat.monster.maxHit}</p>
                        </div>
                    </section>
                )}

                {combat.lastEvent && <div className="combat-event">{combat.lastEvent}</div>}

                {combat.status !== 'active' && (
                    <div className={`combat-result ${combat.status}`}>
                        <strong>{combat.status === 'won' ? 'Pergalė' : combat.status === 'lost' ? 'Pralaimėjimas' : 'Kova nutraukta'}</strong>
                        <button type="button" onClick={() => router.visit('/dashboard')}>Grįžti į World</button>
                    </div>
                )}

                {combat.status === 'active' && (
                    <button className="combat-leave" type="button" onClick={() => router.post('/game/combat/leave')}>Pasitraukti iš kovos</button>
                )}
            </main>
        </div>
    );
}
