import { Head, router } from '@inertiajs/react';
import {
    Backpack,
    Box,
    ChevronRight,
    Cog,
    Landmark,
    MapPin,
    Shield,
    Sparkles,
    Swords,
    TreePine,
    UserRound,
    Users,
} from 'lucide-react';
import { useRef, useState } from 'react';

type Tab = 'combat' | 'skills' | 'inventory' | 'prayer' | 'settings';
type Skill = { xp: number; level: number };
type MapNode = { id: number; name: string; type: string; x: number; y: number };
type MapEdge = { from: number; to: number };

interface Props {
    player: {
        name: string;
        hitpoints: number;
        maxHitpoints: number;
        prayerPoints: number;
        combatLevel: number;
    };
    location: {
        id: number;
        name: string;
        slug: string;
        region: string;
        description: string;
        connections: { id: number; name: string; travelSeconds: number }[];
    };
    worldMap: {
        nodes: MapNode[];
        edges: MapEdge[];
    };
    skills: Record<string, Skill>;
    inventory: { id: number; name: string; icon: string; quantity: number }[];
    flash: { game?: string };
}

const skillNames = [
    'attack',
    'strength',
    'defence',
    'hitpoints',
    'ranged',
    'magic',
    'prayer',
    'woodcutting',
    'mining',
    'fishing',
    'cooking',
];

const locationCategories = [
    { key: 'monsters', label: 'Monstrai', icon: Swords },
    { key: 'npcs', label: 'Personažai', icon: Users },
    { key: 'resources', label: 'Resursai', icon: TreePine },
    { key: 'objects', label: 'Objektai', icon: Box },
];

export default function Game({ player, location, worldMap, skills, inventory, flash }: Props) {
    const [tab, setTab] = useState<Tab | null>(null);
    const [mapOffset, setMapOffset] = useState({ x: 0, y: 0 });
    const drag = useRef<{ pointerId: number; x: number; y: number; offsetX: number; offsetY: number } | null>(null);
    const post = (url: string) => router.post(url, {}, { preserveScroll: true });
    const skill = (name: string) => skills[name] ?? { level: name === 'hitpoints' ? 10 : 1, xp: 0 };
    const connectedIds = new Set(location.connections.map((connection) => connection.id));
    const nodeById = new Map(worldMap.nodes.map((node) => [node.id, node]));

    const beginMapDrag = (event: React.PointerEvent<HTMLDivElement>) => {
        event.currentTarget.setPointerCapture(event.pointerId);
        drag.current = {
            pointerId: event.pointerId,
            x: event.clientX,
            y: event.clientY,
            offsetX: mapOffset.x,
            offsetY: mapOffset.y,
        };
    };

    const moveMap = (event: React.PointerEvent<HTMLDivElement>) => {
        if (!drag.current || drag.current.pointerId !== event.pointerId) return;

        setMapOffset({
            x: drag.current.offsetX + event.clientX - drag.current.x,
            y: drag.current.offsetY + event.clientY - drag.current.y,
        });
    };

    const endMapDrag = (event: React.PointerEvent<HTMLDivElement>) => {
        if (drag.current?.pointerId === event.pointerId) drag.current = null;
    };

    return (
        <div className="game-shell">
            <Head title={`${location.name} · RuneVentures`} />

            <header className="player-bar">
                <button className="avatar" onClick={() => setTab(null)} aria-label="Atverti vietovę">
                    <UserRound size={20} />
                </button>
                <div className="player-copy">
                    <strong>{player.name}</strong>
                    <span>Combat {player.combatLevel}</span>
                </div>
                <div className="vitals">
                    <span>HP {player.hitpoints}/{player.maxHitpoints}</span>
                    <div>
                        <i style={{ width: `${(player.hitpoints / player.maxHitpoints) * 100}%` }} />
                    </div>
                </div>
            </header>

            <main>
                {flash.game && <div className="game-toast">{flash.game}</div>}

                {tab === null && (
                    <>
                        <div className="current-location-line">
                            <span>Lokacija</span>
                            <strong>{location.name}</strong>
                        </div>

                        <section className="world-map-section">
                            <div className="world-map-title">
                                <div>
                                    <span>Vietovės žemėlapis</span>
                                    <small>Tempk žemėlapį pirštu</small>
                                </div>
                                <Landmark size={19} />
                            </div>

                            <div
                                className="world-map-viewport"
                                onPointerDown={beginMapDrag}
                                onPointerMove={moveMap}
                                onPointerUp={endMapDrag}
                                onPointerCancel={endMapDrag}
                            >
                                <div
                                    className="world-map-canvas"
                                    style={{ transform: `translate3d(${mapOffset.x}px, ${mapOffset.y}px, 0)` }}
                                >
                                    <svg className="world-map-lines" viewBox="0 0 760 300" aria-hidden="true">
                                        {worldMap.edges.map((edge) => {
                                            const from = nodeById.get(edge.from);
                                            const to = nodeById.get(edge.to);
                                            if (!from || !to) return null;

                                            return (
                                                <line
                                                    key={`${edge.from}-${edge.to}`}
                                                    x1={from.x}
                                                    y1={from.y}
                                                    x2={to.x}
                                                    y2={to.y}
                                                />
                                            );
                                        })}
                                    </svg>

                                    {worldMap.nodes.map((node) => {
                                        const isCurrent = node.id === location.id;
                                        const canTravel = connectedIds.has(node.id);

                                        return (
                                            <button
                                                key={node.id}
                                                type="button"
                                                className={`world-map-node${isCurrent ? ' current' : ''}${canTravel ? ' reachable' : ''}`}
                                                style={{ left: node.x, top: node.y }}
                                                onPointerDown={(event) => event.stopPropagation()}
                                                onClick={() => {
                                                    if (canTravel && !isCurrent) post(`/game/travel/${node.id}`);
                                                }}
                                                disabled={!isCurrent && !canTravel}
                                            >
                                                <span className="world-map-dot"><MapPin size={17} /></span>
                                                <span className="world-map-node-copy">
                                                    <small>{node.type}</small>
                                                    <strong>{node.name}</strong>
                                                    {canTravel && !isCurrent && <em>Keliauti</em>}
                                                    {isCurrent && <em>Dabartinė vieta</em>}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        </section>

                        <section className="location-menu-section">
                            <div className="location-menu-heading">
                                <div>
                                    <strong>{location.name}</strong>
                                    <span>{location.description}</span>
                                </div>
                            </div>

                            <div className="location-category-grid">
                                {locationCategories.map(({ key, label, icon: Icon }) => (
                                    <button
                                        key={key}
                                        type="button"
                                        onClick={() => router.visit(`/game/location/${location.id}/${key}`)}
                                    >
                                        <Icon size={18} />
                                        <span>{label}</span>
                                        <ChevronRight size={16} />
                                    </button>
                                ))}
                            </div>
                        </section>
                    </>
                )}

                {tab === 'combat' && (
                    <Panel title="Combat">
                        <div className="combat-level-card">
                            <span>Combat level</span>
                            <strong>{player.combatLevel}</strong>
                        </div>
                        <div className="stats-grid">
                            {['attack', 'strength', 'defence', 'hitpoints', 'ranged', 'magic'].map((name) => (
                                <Stat key={name} name={name} value={skill(name).level} />
                            ))}
                        </div>
                        <h3>Attack style</h3>
                        <div className="choice-row">
                            <button>Accurate</button>
                            <button>Aggressive</button>
                            <button>Defensive</button>
                        </div>
                    </Panel>
                )}

                {tab === 'skills' && (
                    <Panel title="Skills">
                        <div className="skill-list">
                            {skillNames.map((name) => (
                                <div className="skill-row" key={name}>
                                    <span>{name}</span>
                                    <b>{skill(name).level}</b>
                                    <small>{skill(name).xp.toLocaleString()} XP</small>
                                </div>
                            ))}
                        </div>
                    </Panel>
                )}

                {tab === 'inventory' && (
                    <Panel title={`Inventory · ${inventory.length}/28`}>
                        <div className="inventory-grid">
                            {Array.from({ length: 28 }, (_, index) => {
                                const item = inventory[index];
                                return item ? (
                                    <div className="item-slot filled" key={index}>
                                        <span className="item-glyph">{item.icon === 'bones' ? '☠' : '▰'}</span>
                                        <span>{item.name}</span>
                                        <b>{item.quantity}</b>
                                    </div>
                                ) : <div className="item-slot" key={index} />;
                            })}
                        </div>
                    </Panel>
                )}

                {tab === 'prayer' && (
                    <Panel title={`Prayer · ${player.prayerPoints}`}>
                        <div className="prayer-list">
                            {['Thick Skin', 'Burst of Strength', 'Clarity of Thought', 'Sharp Eye'].map((name) => (
                                <button key={name}>
                                    <Sparkles size={18} />
                                    <span>{name}</span>
                                    <small>Locked</small>
                                </button>
                            ))}
                        </div>
                    </Panel>
                )}

                {tab === 'settings' && (
                    <Panel title="Settings">
                        <div className="settings-list">
                            <button onClick={() => router.visit('/settings/profile')}>
                                <span>Account settings</span><ChevronRight size={18} />
                            </button>
                            <button onClick={() => setTab(null)}>
                                <span>Return to location</span><ChevronRight size={18} />
                            </button>
                        </div>
                    </Panel>
                )}
            </main>

            <nav className="bottom-nav">
                <Nav icon={<Swords />} label="Combat" active={tab === 'combat'} onClick={() => setTab('combat')} />
                <Nav icon={<Sparkles />} label="Skills" active={tab === 'skills'} onClick={() => setTab('skills')} />
                <Nav icon={<Backpack />} label="Inventory" active={tab === 'inventory'} onClick={() => setTab('inventory')} />
                <Nav icon={<Shield />} label="Prayer" active={tab === 'prayer'} onClick={() => setTab('prayer')} />
                <Nav icon={<Cog />} label="Settings" active={tab === 'settings'} onClick={() => setTab('settings')} />
            </nav>
        </div>
    );
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="panel">
            <button className="back-to-world" onClick={() => router.visit('/dashboard')}>RuneVentures</button>
            <h1>{title}</h1>
            {children}
        </section>
    );
}

function Stat({ name, value }: { name: string; value: number }) {
    return <div><span>{name}</span><b>{value}</b></div>;
}

function Nav({
    icon,
    label,
    active,
    onClick,
}: {
    icon: React.ReactNode;
    label: string;
    active: boolean;
    onClick: () => void;
}) {
    return <button className={active ? 'active' : ''} onClick={onClick}>{icon}<span>{label}</span></button>;
}
