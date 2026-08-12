import { Head, router } from '@inertiajs/react';
import { Swords } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type XpAward = {
    skill: string;
    amount: number;
};

type CombatLogEntry = {
    id: number;
    actor: 'player' | 'monster';
    damage: number;
    hit: boolean;
    xp: XpAward[];
    atMs: number;
};

type LootItem = {
    slug: string;
    name: string;
    quantity: number;
    icon: string;
};

type FighterState = {
    hp: number;
    maxHp: number;
    attackIntervalMs: number;
    attackSeconds: number;
    maxHit: number;
    attackRoll: number;
    defenceRoll: number;
    hitChance: number;
    nextAttackAtMs: number | null;
};

type CombatState = {
    status: 'ready' | 'active' | 'won' | 'lost' | 'fled';
    serverNowMs: number;
    resultReadyAtMs: number | null;
    damageScale: number;
    style: 'melee' | 'ranged' | 'magic';
    player: FighterState & {
        name: string;
        level: number;
        mana: number;
        maxMana: number;
    };
    monster: FighterState & {
        slug: string;
        name: string;
        level: number;
    };
    combatLog: CombatLogEntry[];
    loot: {
        received: LootItem[];
        lost: LootItem[];
    };
    lastEvent: string | null;
};

interface Props {
    combat: CombatState | null;
}

export default function Combat({ combat: initialCombat }: Props) {
    const [combat, setCombat] = useState(initialCombat);
    const [now, setNow] = useState(Date.now());
    const [serverOffsetMs, setServerOffsetMs] = useState(
        initialCombat ? initialCombat.serverNowMs - Date.now() : 0,
    );
    const [attackPulse, setAttackPulse] = useState<'player' | 'monster' | null>(null);
    const lastAnimatedLogId = useRef(
        initialCombat?.combatLog.at(-1)?.id ?? 0,
    );

    useEffect(() => {
        setCombat(initialCombat);
        if (initialCombat) {
            setServerOffsetMs(initialCombat.serverNowMs - Date.now());
        }
    }, [initialCombat]);

    useEffect(() => {
        const clock = window.setInterval(() => setNow(Date.now()), 50);
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
            if (!cancelled && data.combat) {
                setCombat(data.combat);
                setServerOffsetMs(data.combat.serverNowMs - Date.now());
            }
        };

        const poller = window.setInterval(refresh, 220);
        refresh();

        return () => {
            cancelled = true;
            window.clearInterval(poller);
        };
    }, [combat?.status]);

    useEffect(() => {
        if (!combat || combat.combatLog.length === 0) return;

        const latest = combat.combatLog.at(-1);
        if (!latest || latest.id <= lastAnimatedLogId.current) return;

        lastAnimatedLogId.current = latest.id;
        setAttackPulse(latest.actor);

        const timer = window.setTimeout(() => setAttackPulse(null), 320);
        return () => window.clearTimeout(timer);
    }, [combat?.combatLog]);

    const serverNow = now + serverOffsetMs;
    const remaining = (endsAtMs: number | null) => {
        if (!endsAtMs) return 0;
        return Math.max(0, endsAtMs - serverNow);
    };

    if (!combat) {
        return (
            <div className="game-shell combat-page">
                <Head title="Combat · RuneVentures" />
                <main className="combat-empty">
                    <Swords size={30} />
                    <h1>Nėra aktyvios kovos</h1>
                    <button type="button" onClick={() => router.visit('/main')}>Grįžti į World</button>
                </main>
            </div>
        );
    }

    const terminal = combat.status === 'won' || combat.status === 'lost';
    const resultReady = terminal && (
        combat.resultReadyAtMs === null || serverNow >= combat.resultReadyAtMs
    );
    const resultRemaining = terminal && combat.resultReadyAtMs
        ? Math.max(0, combat.resultReadyAtMs - serverNow)
        : 0;

    if (resultReady) {
        return (
            <div className="game-shell combat-page">
                <Head title={`Combat result · ${combat.monster.name} · RuneVentures`} />
                <main className="combat-results-page">
                    <section className={`combat-result-card ${combat.status}`}>
                        <span>Combat result</span>
                        <h1>{combat.status === 'won' ? 'Pergalė' : 'Pralaimėjimas'}</h1>
                        <p>
                            {combat.status === 'won'
                                ? `${combat.monster.name} nugalėtas.`
                                : `${combat.player.name} buvo nugalėtas.`}
                        </p>
                    </section>

                    {combat.status === 'won' && (
                        <section className="combat-loot-panel">
                            <div className="combat-section-heading">
                                <span>Drop</span>
                                <small>Automatiškai pridėta į inventorių</small>
                            </div>

                            <div className="combat-loot-list">
                                {combat.loot.received.length === 0 && (
                                    <div className="combat-loot-empty">Nieko neiškrito.</div>
                                )}

                                {combat.loot.received.map((item, index) => (
                                    <div className="combat-loot-row" key={`${item.slug}-${index}`}>
                                        <strong>{item.name}</strong>
                                        <b>×{item.quantity}</b>
                                    </div>
                                ))}
                            </div>

                            {combat.loot.lost.length > 0 && (
                                <div className="combat-loot-lost">
                                    Inventoriuje netilpo: {combat.loot.lost.map((item) => `${item.name} ×${item.quantity}`).join(', ')}
                                </div>
                            )}
                        </section>
                    )}

                    <button className="combat-result-return" type="button" onClick={() => router.visit('/main')}>
                        Grįžti į World
                    </button>
                </main>
            </div>
        );
    }

    const playerRemaining = remaining(combat.player.nextAttackAtMs);
    const monsterRemaining = remaining(combat.monster.nextAttackAtMs);
    const playerProgress = combat.status === 'active' && combat.player.attackIntervalMs > 0
        ? Math.max(0, Math.min(100, 100 - (playerRemaining / combat.player.attackIntervalMs) * 100))
        : 0;
    const monsterProgress = combat.status === 'active' && combat.monster.attackIntervalMs > 0
        ? Math.max(0, Math.min(100, 100 - (monsterRemaining / combat.monster.attackIntervalMs) * 100))
        : 0;

    return (
        <div className="game-shell combat-page">
            <Head title={`${combat.monster.name} · Combat · RuneVentures`} />

            <main>
                <section className={`combat-duel${terminal ? ' ending' : ''}`}>
                    <article className={`duel-side player-side${combat.status === 'lost' ? ' dead' : ''}`}>
                        <div className="duel-identity">
                            <strong>{combat.player.name}</strong>
                            <span>Level {combat.player.level}</span>
                        </div>

                        <CombatBar label="HP" value={combat.player.hp} max={combat.player.maxHp} kind="hp" />
                        <CombatBar label="MP" value={combat.player.mana} max={combat.player.maxMana} kind="mana" />

                        <AttackOrb
                            side="player"
                            progress={playerProgress}
                            remaining={playerRemaining}
                            interval={combat.player.attackIntervalMs}
                            firing={attackPulse === 'player'}
                            active={combat.status === 'active'}
                        />
                    </article>

                    <div className="duel-divider"><Swords size={19} /></div>

                    <article className={`duel-side monster-side${combat.status === 'won' ? ' dead' : ''}`}>
                        <div className="duel-identity">
                            <strong>{combat.monster.name}</strong>
                            <span>Level {combat.monster.level}</span>
                        </div>

                        <CombatBar label="HP" value={combat.monster.hp} max={combat.monster.maxHp} kind="hp" />

                        <AttackOrb
                            side="monster"
                            progress={monsterProgress}
                            remaining={monsterRemaining}
                            interval={combat.monster.attackIntervalMs}
                            firing={attackPulse === 'monster'}
                            active={combat.status === 'active'}
                        />
                    </article>
                </section>

                {combat.status === 'ready' && (
                    <button
                        className="combat-hit-start"
                        type="button"
                        onClick={() => router.post('/game/combat/hit')}
                    >
                        Hit
                    </button>
                )}

                {terminal && (
                    <div className="combat-death-cooldown">
                        <strong>{combat.status === 'won' ? `${combat.monster.name} mirė` : `${combat.player.name} mirė`}</strong>
                        <span>Rezultatai po {(resultRemaining / 1000).toFixed(1)} s</span>
                    </div>
                )}

                <section className="combat-log-panel">
                    <div className="combat-section-heading">
                        <span>Combat log</span>
                        <small>{combat.combatLog.length} įrašai</small>
                    </div>

                    <div className="combat-log-list">
                        {combat.combatLog.length === 0 && (
                            <div className="combat-log-empty">
                                {combat.status === 'ready' ? 'Paspausk Hit, kad prasidėtų automatinė kova.' : 'Laukiama pirmo smūgio...'}
                            </div>
                        )}

                        {[...combat.combatLog].reverse().map((entry) => (
                            <div className={`combat-log-row ${entry.actor}`} key={entry.id}>
                                <div>
                                    <strong>{entry.actor === 'player' ? combat.player.name : combat.monster.name}</strong>
                                    <span>{entry.hit ? `${entry.damage} damage` : 'Miss'}</span>
                                </div>
                                {entry.xp.length > 0 && (
                                    <small>{entry.xp.map((award) => `+${award.amount} ${capitalize(award.skill)} XP`).join(' · ')}</small>
                                )}
                            </div>
                        ))}
                    </div>
                </section>

                {(combat.status === 'ready' || combat.status === 'active') && (
                    <button className="combat-leave" type="button" onClick={() => router.post('/game/combat/leave')}>
                        Pasitraukti iš kovos
                    </button>
                )}
            </main>
        </div>
    );
}

function CombatBar({
    label,
    value,
    max,
    kind,
}: {
    label: string;
    value: number;
    max: number;
    kind: 'hp' | 'mana';
}) {
    const progress = Math.max(0, Math.min(100, (value / Math.max(1, max)) * 100));

    return (
        <div className={`duel-vital ${kind}`}>
            <div className="duel-vital-line">
                <span>{label}</span>
                <b>{value}/{max}</b>
            </div>
            <div className="duel-vital-bar"><i style={{ width: `${progress}%` }} /></div>
        </div>
    );
}

function AttackOrb({
    side,
    progress,
    remaining,
    interval,
    firing,
    active,
}: {
    side: 'player' | 'monster';
    progress: number;
    remaining: number;
    interval: number;
    firing: boolean;
    active: boolean;
}) {
    const angle = progress * 3.6;

    return (
        <div className={`attack-orb-wrap ${side}`}>
            <div
                className={`attack-orb ${side}${firing ? ' firing' : ''}${active ? ' active' : ''}`}
                style={{
                    background: `conic-gradient(#b88a67 ${angle}deg, #252226 ${angle}deg 360deg)`,
                }}
            >
                <i />
            </div>
            <span>{active ? `${(remaining / 1000).toFixed(1)} s` : 'Ready'}</span>
            <small>{(interval / 1000).toFixed(1)} s rate</small>
        </div>
    );
}

function capitalize(value: string) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}
