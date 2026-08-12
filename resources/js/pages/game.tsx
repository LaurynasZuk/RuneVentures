import { Head, router } from '@inertiajs/react';
import {
    Backpack,
    Box,
    ChevronRight,
    Cog,
    CookingPot,
    Crosshair,
    Dumbbell,
    Feather,
    Fish,
    Flame,
    FlaskConical,
    Footprints,
    Gem,
    Hammer,
    Hand,
    Heart,
    House,
    LockKeyhole,
    Orbit,
    Package,
    PawPrint,
    Pickaxe,
    Sailboat,
    Shield,
    Skull,
    Sparkles,
    Sprout,
    Swords,
    TreePine,
    UserRound,
    Users,
    WandSparkles,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Tab = 'combat' | 'skills' | 'inventory' | 'prayer' | 'settings';
type InventoryView = 'items' | 'equipment';
type Skill = { xp: number; level: number };
type MapNode = { id: number; name: string; x: number; y: number };
type MapEdge = { from: number; to: number };
type TravelState = {
    endsAt: string;
    remainingSeconds: number;
} | null;
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
    travel: TravelState;
    skills: Record<string, Skill>;
    inventory: InventoryStack[];
    inventoryCapacity: number;
    backpacks: BackpackSlot[];
    equipment: Record<string, EquippedItem>;
    equipmentSlots: string[];
    flash: { game?: string };
}

const skillNames = [
    'attack', 'hitpoints', 'mining',
    'strength', 'agility', 'smithing',
    'defence', 'herblore', 'fishing',
    'ranged', 'thieving', 'cooking',
    'prayer', 'crafting', 'firemaking',
    'magic', 'fletching', 'woodcutting',
    'runecraft', 'slayer', 'farming',
    'construction', 'hunter', 'sailing',
];

const skillDescriptions: Record<string, string> = {
    attack: 'Didina artimos kovos smūgių tikslumą ir leidžia naudoti aukštesnio lygio ginklus.',
    hitpoints: 'Nustato, kiek žalos veikėjas gali atlaikyti prieš pralaimėdamas kovą.',
    mining: 'Leidžia kasti rūdą, akmenis ir kitus mineralinius resursus.',
    strength: 'Didina artimos kovos žalą ir maksimalų smūgį.',
    agility: 'Skirta judėjimui, kliūtims, trumpesniems keliams ir mobilumui.',
    smithing: 'Leidžia lydyti metalą ir gaminti metalinius ginklus bei šarvus.',
    defence: 'Didina gynybines galimybes ir leidžia naudoti stipresnius šarvus.',
    herblore: 'Leidžia apdoroti žoleles ir gaminti įvairius eliksyrus.',
    fishing: 'Leidžia gaudyti žuvis ir kitus vandens resursus.',
    ranged: 'Valdo nuotolinės kovos tikslumą ir aukštesnio lygio ranged įrangą.',
    thieving: 'Leidžia vogti iš personažų, skrynių ir kitų objektų.',
    cooking: 'Leidžia gaminti maistą ir kitus vartojamus patiekalus.',
    prayer: 'Kiekvienas Prayer lygis suteikia 100 Prayer Mana.',
    crafting: 'Leidžia gaminti papuošalus, odos gaminius ir kitus daiktus.',
    firemaking: 'Leidžia kurti ir naudoti skirtingo lygio laužus bei ugnį.',
    magic: 'Valdo magijos burtus, jų tikslumą ir aukštesnio lygio magišką įrangą.',
    fletching: 'Leidžia gaminti lankus, strėles ir kitą nuotolinės kovos amuniciją.',
    woodcutting: 'Leidžia kirsti medžius ir gauti skirtingos rūšies medieną.',
    runecraft: 'Leidžia kurti runas, naudojamas magijai ir kitoms sistemoms.',
    slayer: 'Leidžia kovoti su specialiais monstrais ir vykdyti Slayer užduotis.',
    farming: 'Leidžia auginti žoleles, augalus ir kitus ūkininkavimo resursus.',
    construction: 'Leidžia statyti ir tobulinti žaidėjo pastatus bei infrastruktūrą.',
    hunter: 'Leidžia sekti, gaudyti ir medžioti laukinius padarus.',
    sailing: 'Leidžia valdyti laivus, keliauti jūra ir vykdyti veiklas vandenyne.',
};

const combatStances = [
    {
        key: 'accurate',
        name: 'Accurate',
        icon: Crosshair,
        description: '+3 Attack. Didesnis melee tikslumas; kovos XP skiriamas Attack.',
    },
    {
        key: 'aggressive',
        name: 'Aggressive',
        icon: Swords,
        description: '+3 Strength. Didesnis melee smūgio potencialas; kovos XP skiriamas Strength.',
    },
    {
        key: 'defensive',
        name: 'Defensive',
        icon: Shield,
        description: '+3 Defence. Sustiprina gynybinę poziciją; kovos XP skiriamas Defence.',
    },
    {
        key: 'controlled',
        name: 'Controlled',
        icon: Dumbbell,
        description: '+1 Attack, +1 Strength ir +1 Defence. Kovos XP dalijamas tarp šių trijų skillų.',
    },
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
    travel,
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
    const [selectedSkill, setSelectedSkill] = useState<string | null>(null);
    const [combatStance, setCombatStance] = useState('accurate');
    const [selectedMapNode, setSelectedMapNode] = useState<number | null>(null);
    const [travelRemaining, setTravelRemaining] = useState(travel?.remainingSeconds ?? 0);
    const [mapOffset, setMapOffset] = useState({ x: 0, y: 0 });
    const drag = useRef<{ pointerId: number; x: number; y: number; offsetX: number; offsetY: number } | null>(null);
    const post = (url: string) => router.post(url, {}, { preserveScroll: true });
    const skill = (name: string) => skills[name] ?? { level: name === 'hitpoints' ? 10 : 1, xp: 0 };
    const connectedIds = new Set(location.connections.map((connection) => connection.id));
    const nodeById = new Map(worldMap.nodes.map((node) => [node.id, node]));
    const inventoryBySlot = new Map(inventory.map((item) => [item.slot, item]));
    const selectedNode = worldMap.nodes.find((node) => node.id === selectedMapNode) ?? null;
    const selectedConnection = location.connections.find((connection) => connection.id === selectedMapNode) ?? null;
    const travelOnCooldown = travelRemaining > 0;

    useEffect(() => {
        if (!travel) {
            setTravelRemaining(0);
            return;
        }

        let timer: number | undefined;

        const updateCountdown = () => {
            const remaining = Math.max(
                0,
                Math.ceil((new Date(travel.endsAt).getTime() - Date.now()) / 1000),
            );

            setTravelRemaining(remaining);

            if (remaining <= 0 && timer !== undefined) {
                window.clearInterval(timer);
            }
        };

        updateCountdown();
        timer = window.setInterval(updateCountdown, 250);

        return () => {
            if (timer !== undefined) window.clearInterval(timer);
        };
    }, [travel?.endsAt]);

    useEffect(() => {
        setSelectedMapNode(null);
    }, [location.id]);

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
                                    <svg className="world-map-lines" viewBox="0 0 700 640" aria-hidden="true">
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
                                        const isReachable = connectedIds.has(node.id);
                                        const isSelected = node.id === selectedMapNode;

                                        return (
                                            <button
                                                key={node.id}
                                                type="button"
                                                className={[
                                                    'world-map-node',
                                                    isCurrent ? 'current' : '',
                                                    isReachable ? 'reachable' : '',
                                                    isSelected ? 'selected' : '',
                                                ].filter(Boolean).join(' ')}
                                                style={{ left: node.x, top: node.y }}
                                                onPointerDown={(event) => event.stopPropagation()}
                                                onClick={() => {
                                                    if (!isCurrent) setSelectedMapNode(node.id);
                                                }}
                                                aria-current={isCurrent ? 'location' : undefined}
                                            >
                                                <span className="world-map-node-name">{node.name}</span>
                                                <span className="world-map-dot" />
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {selectedNode ? (
                                <div className="travel-action-panel">
                                    <div>
                                        <span>Pasirinkta vietovė</span>
                                        <strong>{selectedNode.name}</strong>
                                    </div>
                                    <button
                                        type="button"
                                        disabled={!selectedConnection || travelOnCooldown}
                                        onClick={() => {
                                            if (selectedConnection && !travelOnCooldown) {
                                                post(`/game/travel/${selectedConnection.id}`);
                                            }
                                        }}
                                    >
                                        {!selectedConnection
                                            ? 'Nėra tiesioginio kelio'
                                            : travelOnCooldown
                                                ? `Keliauti po ${travelRemaining} s`
                                                : 'Keliauti'}
                                    </button>
                                </div>
                            ) : travelOnCooldown ? (
                                <div className="travel-action-panel traveling">
                                    <div>
                                        <span>Kitas perėjimas</span>
                                        <strong>{location.name}</strong>
                                    </div>
                                    <button type="button" disabled>
                                        {travelRemaining} s
                                    </button>
                                </div>
                            ) : null}
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
                        <div className="combat-level-card osrs-combat-level combat-level-only">
                            <span>Combat level</span>
                            <strong>{player.combatLevel}</strong>
                        </div>

                        <h3>Combat stance</h3>
                        <div className="combat-stance-list">
                            {combatStances.map(({ key, name, icon: Icon, description }) => (
                                <button
                                    key={key}
                                    type="button"
                                    className={combatStance === key ? 'active' : ''}
                                    onClick={() => setCombatStance(key)}
                                >
                                    <span className="combat-stance-icon"><Icon size={19} /></span>
                                    <span className="combat-stance-copy">
                                        <strong>{name}</strong>
                                        <small>{description}</small>
                                    </span>
                                </button>
                            ))}
                        </div>
                    </Panel>
                )}

                {tab === 'skills' && (
                    <Panel title="Stats">
                        <div className="osrs-skill-grid">
                            {skillNames.map((name) => {
                                const data = skill(name);

                                return (
                                    <button
                                        className="osrs-skill-tile"
                                        key={name}
                                        type="button"
                                        onClick={() => setSelectedSkill(name)}
                                        aria-label={`${capitalize(name)} ${data.level}/${data.level}`}
                                    >
                                        <span className="osrs-skill-icon">{skillIcon(name, 23)}</span>
                                        <b>{data.level}/{data.level}</b>
                                    </button>
                                );
                            })}
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

            {selectedSkill && (
                <div className="skill-modal-backdrop" onClick={() => setSelectedSkill(null)}>
                    <section className="skill-modal" onClick={(event) => event.stopPropagation()}>
                        <button
                            className="skill-modal-close"
                            type="button"
                            onClick={() => setSelectedSkill(null)}
                            aria-label="Uždaryti"
                        >
                            <X size={18} />
                        </button>

                        <div className="skill-modal-heading">
                            <span>{skillIcon(selectedSkill, 28)}</span>
                            <div>
                                <small>Skill</small>
                                <h2>{capitalize(selectedSkill)}</h2>
                            </div>
                        </div>

                        <div className="skill-modal-stats">
                            <div>
                                <span>Level</span>
                                <strong>{skill(selectedSkill).level}/{skill(selectedSkill).level}</strong>
                            </div>
                            <div>
                                <span>XP</span>
                                <strong>{skill(selectedSkill).xp.toLocaleString()}</strong>
                            </div>
                            <div>
                                <span>Max</span>
                                <strong>99</strong>
                            </div>
                        </div>

                        <p>{skillDescriptions[selectedSkill]}</p>
                    </section>
                </div>
            )}
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

function skillIcon(name: string, size = 18) {
    if (name === 'attack') return <Swords size={size} />;
    if (name === 'hitpoints') return <Heart size={size} />;
    if (name === 'mining') return <Pickaxe size={size} />;
    if (name === 'strength') return <Dumbbell size={size} />;
    if (name === 'agility') return <Footprints size={size} />;
    if (name === 'smithing') return <Hammer size={size} />;
    if (name === 'defence') return <Shield size={size} />;
    if (name === 'herblore') return <FlaskConical size={size} />;
    if (name === 'fishing') return <Fish size={size} />;
    if (name === 'ranged') return <Crosshair size={size} />;
    if (name === 'thieving') return <Hand size={size} />;
    if (name === 'cooking') return <CookingPot size={size} />;
    if (name === 'prayer') return <Sparkles size={size} />;
    if (name === 'crafting') return <Gem size={size} />;
    if (name === 'firemaking') return <Flame size={size} />;
    if (name === 'magic') return <WandSparkles size={size} />;
    if (name === 'fletching') return <Feather size={size} />;
    if (name === 'woodcutting') return <TreePine size={size} />;
    if (name === 'runecraft') return <Orbit size={size} />;
    if (name === 'slayer') return <Skull size={size} />;
    if (name === 'farming') return <Sprout size={size} />;
    if (name === 'construction') return <House size={size} />;
    if (name === 'hunter') return <PawPrint size={size} />;
    if (name === 'sailing') return <Sailboat size={size} />;
    return <Package size={size} />;
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
