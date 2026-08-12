import { Head, router } from '@inertiajs/react';
import {
    Backpack,
    Box,
    ChevronRight,
    Cog,
    Crosshair,
    Heart,
    Landmark,
    LockKeyhole,
    MapPin,
    Package,
    Shield,
    Sparkles,
    Swords,
    TreePine,
    UserRound,
    Users,
    WandSparkles,
} from 'lucide-react';
import { useRef, useState } from 'react';

type Tab = 'combat' | 'skills' | 'inventory' | 'prayer' | 'settings';
type InventoryView = 'items' | 'equipment';
type Skill = { xp: number; level: number };
type MapNode = { id: number; name: string; type: string; x: number; y: number };
type MapEdge = { from: number; to: number };
type InventoryStack = {
    slot: number;
    id: number;
    name: string;
    icon: string;
    quantity: number;
    stackLimit: number | null;
};
type EquippedItem = { id: number; name: string; icon: string } | null;
type BackpackSlot = {
    slot: number;
    unlocked: boolean;
    item: { id: number; name: string; icon: string; slotsBonus: number } | null;
};

interface Props {
    player: {
        name: string;
        hitpoints: number;
        maxHitpoints: number;
        prayerMana: number;
        maxPrayerMana: number;
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
    inventory: InventoryStack[];
    inventoryCapacity: number;
    backpacks: BackpackSlot[];
    equipment: Record<string, EquippedItem>;
    equipmentSlots: string[];
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

const combatSkillNames = [
    'attack',
    'strength',
    'defence',
    'hitpoints',
    'ranged',
    'magic',
    'prayer',
];

const equipmentLabels: Record<string, string> = {
    head: 'Head',
    cape: 'Cape',
    neck: 'Neck',
    ammo: 'Ammo',
    weapon: 'Weapon',
    body: 'Body',
    shield: 'Shield',
    legs: 'Legs',
    hands: 'Hands',
    feet: 'Feet',
    ring: 'Ring',
};

const locationCategories = [
    { key: 'monsters', label: 'Monstrai', icon: Swords },
    { key: 'npcs', label: 'Personažai', icon: Users },
    { key: 'resources', label: 'Resursai', icon: TreePine },
    { key: 'objects', label: 'Objektai', icon: Box },
];

export default function Game({
    player,
    location,
    worldMap,
    skills,
    inventory,
    inventoryCapacity,
    backpacks,
    equipment,
    equipmentSlots,
    flash,
}: Props) {
    const [tab, setTab] = useState<Tab | null>(null);
    const [inventoryView, setInventoryView] = useState<InventoryView>('items');
    const [mapOffset, setMapOffset] = useState({ x: 0, y: 0 });
    const drag = useRef<{ pointerId: number; x: number; y: number; offsetX: number; offsetY: number } | null>(null);
    const post = (url: string) => router.post(url, {}, { preserveScroll: true });
    const skill = (name: string) => skills[name] ?? { level: name === 'hitpoints' ? 10 : 1, xp: 0 };
    const connectedIds = new Set(location.connections.map((connection) => connection.id));
    const nodeById = new Map(worldMap.nodes.map((node) => [node.id, node]));
    const inventoryBySlot = new Map(inventory.map((item) => [item.slot, item]));

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
                        <div className="combat-level-card osrs-combat-level">
                            <span>Combat level</span>
                            <strong>{player.combatLevel}</strong>
                        </div>

                        <div className="combat-stats-grid">
                            {combatSkillNames.map((name) => (
                                <CombatStat key={name} name={name} level={skill(name).level} />
                            ))}
                        </div>

                        <h3>Attack style</h3>
                        <div className="choice-row osrs-attack-styles">
                            <button><Crosshair size={16} />Accurate</button>
                            <button><Swords size={16} />Aggressive</button>
                            <button><Shield size={16} />Defensive</button>
                        </div>
                    </Panel>
                )}

                {tab === 'skills' && (
                    <Panel title="Stats">
                        <div className="osrs-skill-grid">
                            {skillNames.map((name) => (
                                <div className="osrs-skill-tile" key={name}>
                                    <span className="osrs-skill-icon">{skillIcon(name)}</span>
                                    <div>
                                        <strong>{capitalize(name)}</strong>
                                        <b>{skill(name).level}</b>
                                    </div>
                                    <small>{skill(name).xp.toLocaleString()} XP</small>
                                </div>
                            ))}
                        </div>
                    </Panel>
                )}

                {tab === 'inventory' && (
                    <Panel title="Inventory">
                        <div className="inventory-view-tabs">
                            <button
                                className={inventoryView === 'items' ? 'active' : ''}
                                onClick={() => setInventoryView('items')}
                            >
                                <Backpack size={16} /> Inventory
                            </button>
                            <button
                                className={inventoryView === 'equipment' ? 'active' : ''}
                                onClick={() => setInventoryView('equipment')}
                            >
                                <Shield size={16} /> Equipment
                            </button>
                        </div>

                        {inventoryView === 'items' && (
                            <>
                                <div className="inventory-summary">
                                    <div>
                                        <span>Base slots</span>
                                        <strong>25</strong>
                                    </div>
                                    <div>
                                        <span>Total slots</span>
                                        <strong>{inventoryCapacity}</strong>
                                    </div>
                                </div>

                                <div className="backpack-section-title">
                                    <span>Kuprinės</span>
                                    <small>1 aktyvus slotas · 4 atrakinami talentais</small>
                                </div>

                                <div className="backpack-slots">
                                    {backpacks.map((backpack) => (
                                        <div
                                            className={`backpack-slot${backpack.unlocked ? ' unlocked' : ' locked'}`}
                                            key={backpack.slot}
                                        >
                                            {!backpack.unlocked ? (
                                                <>
                                                    <LockKeyhole size={18} />
                                                    <span>{backpack.slot}</span>
                                                </>
                                            ) : backpack.item ? (
                                                <>
                                                    <Backpack size={20} />
                                                    <strong>{backpack.item.name}</strong>
                                                    <small>+{backpack.item.slotsBonus}</small>
                                                </>
                                            ) : (
                                                <>
                                                    <Backpack size={20} />
                                                    <span>Tuščia</span>
                                                </>
                                            )}
                                        </div>
                                    ))}
                                </div>

                                <div className="inventory-grid inventory-grid-25">
                                    {Array.from({ length: inventoryCapacity }, (_, index) => {
                                        const slotNumber = index + 1;
                                        const item = inventoryBySlot.get(slotNumber);

                                        return item ? (
                                            <div className="item-slot filled" key={slotNumber}>
                                                <span className="item-glyph">{itemGlyph(item.icon)}</span>
                                                <span>{item.name}</span>
                                                {item.quantity > 1 && <b>{item.quantity}</b>}
                                                <em>{item.stackLimit === null ? '∞' : `/${item.stackLimit}`}</em>
                                            </div>
                                        ) : (
                                            <div className="item-slot" key={slotNumber}>
                                                <small>{slotNumber}</small>
                                            </div>
                                        );
                                    })}
                                </div>
                            </>
                        )}

                        {inventoryView === 'equipment' && (
                            <>
                                <div className="equipment-caption">
                                    <span>Equipment</span>
                                    <small>OSRS tipo 11 įrangos slotų</small>
                                </div>
                                <div className="equipment-grid">
                                    {equipmentSlots.map((slot) => {
                                        const item = equipment[slot];
                                        return (
                                            <div className={`equipment-slot slot-${slot}${item ? ' equipped' : ''}`} key={slot}>
                                                {item ? (
                                                    <>
                                                        <Package size={20} />
                                                        <strong>{item.name}</strong>
                                                    </>
                                                ) : (
                                                    <>
                                                        <Shield size={18} />
                                                        <span>{equipmentLabels[slot] ?? capitalize(slot)}</span>
                                                    </>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </>
                        )}
                    </Panel>
                )}

                {tab === 'prayer' && (
                    <Panel title="Prayer">
                        <div className="prayer-mana-card">
                            <div className="prayer-mana-heading">
                                <WandSparkles size={22} />
                                <div>
                                    <span>Prayer Mana</span>
                                    <strong>{player.prayerMana} / {player.maxPrayerMana}</strong>
                                </div>
                            </div>
                            <div className="prayer-mana-bar">
                                <i style={{ width: `${Math.min(100, (player.prayerMana / Math.max(1, player.maxPrayerMana)) * 100)}%` }} />
                            </div>
                            <p>1 Prayer lygis = 100 Mana.</p>
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
                <Nav icon={<Sparkles />} label="Stats" active={tab === 'skills'} onClick={() => setTab('skills')} />
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

function CombatStat({ name, level }: { name: string; level: number }) {
    return (
        <div className="combat-stat-tile">
            <span>{skillIcon(name)}</span>
            <div>
                <small>{capitalize(name)}</small>
                <strong>{level}</strong>
            </div>
        </div>
    );
}

function skillIcon(name: string) {
    if (name === 'hitpoints') return <Heart size={18} />;
    if (name === 'ranged') return <Crosshair size={18} />;
    if (name === 'magic') return <WandSparkles size={18} />;
    if (name === 'prayer') return <Sparkles size={18} />;
    if (name === 'defence') return <Shield size={18} />;
    if (name === 'woodcutting') return <TreePine size={18} />;
    if (name === 'attack' || name === 'strength') return <Swords size={18} />;
    return <Package size={18} />;
}

function itemGlyph(icon: string) {
    if (icon === 'bones') return '☠';
    if (icon === 'logs') return '▰';
    return '◆';
}

function capitalize(value: string) {
    return value.charAt(0).toUpperCase() + value.slice(1);
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
