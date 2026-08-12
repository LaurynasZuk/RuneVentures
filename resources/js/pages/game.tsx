import { Head, router } from '@inertiajs/react';
import {
    Backpack,
    ChevronRight,
    Cog,
    Map,
    Shield,
    Sparkles,
    Swords,
    TreePine,
    UserRound,
    Users,
} from 'lucide-react';
import { useState } from 'react';

type Tab = 'combat' | 'skills' | 'inventory' | 'prayer' | 'settings';
type Skill = { xp: number; level: number };
type ContentItem = {
    name: string;
    detail?: string;
    action?: string;
    requiredLevel?: number;
    level?: number;
    slug?: string;
    xp?: number;
};

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
        region: string;
        description: string;
        connections: { id: number; name: string; travelSeconds: number }[];
        content: {
            objects: ContentItem[];
            npcs: ContentItem[];
            resources: ContentItem[];
            monsters: ContentItem[];
        };
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

export default function Game({ player, location, skills, inventory, flash }: Props) {
    const [tab, setTab] = useState<Tab | null>(null);
    const post = (url: string) => router.post(url, {}, { preserveScroll: true });
    const skill = (name: string) => skills[name] ?? { level: name === 'hitpoints' ? 10 : 1, xp: 0 };

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
                        <section className="location-hero">
                            <div className="eyebrow"><Map size={13} /> {location.region}</div>
                            <h1>{location.name}</h1>
                            <p>{location.description}</p>
                        </section>

                        <GameSection
                            title="Vietovės"
                            icon={<Map size={18} />}
                            items={location.connections.map((connection) => ({
                                name: connection.name,
                                detail: `${connection.travelSeconds} sec kelionė`,
                            }))}
                            onClick={(index) => post(`/game/travel/${location.connections[index].id}`)}
                        />

                        <GameSection title="Objektai" icon={<Shield size={18} />} items={location.content.objects} />
                        <GameSection title="NPC" icon={<Users size={18} />} items={location.content.npcs} />
                        <GameSection
                            title="Resursai"
                            icon={<TreePine size={18} />}
                            items={location.content.resources.map((resource) => ({
                                ...resource,
                                detail: resource.requiredLevel ? `Reikia lygio ${resource.requiredLevel}` : resource.detail,
                            }))}
                            onClick={(index) => {
                                if (location.content.resources[index].action === 'chop') {
                                    post('/game/actions/chop');
                                }
                            }}
                        />
                        <GameSection
                            title="Monstrai"
                            icon={<Swords size={18} />}
                            items={location.content.monsters.map((monster) => ({
                                ...monster,
                                detail: `Combat ${monster.level} · ${monster.xp} XP`,
                            }))}
                            onClick={(index) => {
                                const monster = location.content.monsters[index];
                                if (monster.slug) post(`/game/actions/attack/${monster.slug}`);
                            }}
                        />
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

function GameSection({
    title,
    icon,
    items,
    onClick,
}: {
    title: string;
    icon: React.ReactNode;
    items: ContentItem[];
    onClick?: (index: number) => void;
}) {
    return (
        <section className="game-section">
            <h2>{icon}{title}<span>{items.length}</span></h2>
            <div className="action-list">
                {items.map((item, index) => (
                    <button key={`${item.name}-${index}`} onClick={() => onClick?.(index)}>
                        <span>{item.name}<small>{item.detail}</small></span>
                        <ChevronRight size={18} />
                    </button>
                ))}
            </div>
        </section>
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
