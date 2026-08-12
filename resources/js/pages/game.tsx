import { Head, router } from '@inertiajs/react';
import { Axe, Backpack, ChevronRight, Map, Shield, Sparkles, Swords, TreePine, UserRound, Users } from 'lucide-react';
import { useState } from 'react';

type Tab = 'world' | 'combat' | 'skills' | 'inventory' | 'prayer';
type Skill = { xp: number; level: number };
type ContentItem = { name: string; detail?: string; action?: string; requiredLevel?: number; level?: number };

interface Props {
    player: { name: string; hitpoints: number; maxHitpoints: number; prayerPoints: number };
    location: {
        id: number; name: string; region: string; description: string;
        connections: { id: number; name: string; travelSeconds: number }[];
        content: { objects: ContentItem[]; npcs: ContentItem[]; resources: ContentItem[]; monsters: ContentItem[] };
    };
    skills: Record<string, Skill>;
    inventory: { id: number; name: string; icon: string; quantity: number }[];
    flash: { game?: string };
}

const skillNames = ['attack', 'strength', 'defence', 'hitpoints', 'ranged', 'magic', 'prayer', 'woodcutting', 'mining', 'fishing', 'cooking'];

export default function Game({ player, location, skills, inventory, flash }: Props) {
    const [tab, setTab] = useState<Tab>('world');
    const busy = (url: string) => router.post(url, {}, { preserveScroll: true });
    const skill = (name: string) => skills[name] ?? { level: name === 'hitpoints' ? 10 : 1, xp: 0 };

    return (
        <div className="game-shell">
            <Head title={`${location.name} · RuneVentures`} />
            <header className="player-bar">
                <div className="avatar"><UserRound size={20} /></div>
                <div className="player-copy"><strong>{player.name}</strong><span>Adventurer · Combat 3</span></div>
                <div className="vitals"><span>HP {player.hitpoints}/{player.maxHitpoints}</span><div><i style={{ width: `${player.hitpoints / player.maxHitpoints * 100}%` }} /></div></div>
            </header>

            <main>
                {flash.game && <div className="game-toast">{flash.game}</div>}
                {tab === 'world' && <>
                    <section className="location-hero">
                        <div className="eyebrow"><Map size={13} /> {location.region}</div>
                        <h1>{location.name}</h1>
                        <p>{location.description}</p>
                    </section>
                    <GameSection title="Vietovės" icon={<Map size={18} />} items={location.connections.map(x => ({ name: x.name, detail: `${x.travelSeconds} sec` }))} onClick={(i) => busy(`/game/travel/${location.connections[i].id}`)} />
                    <GameSection title="Objektai" icon={<Shield size={18} />} items={location.content.objects} />
                    <GameSection title="NPC" icon={<Users size={18} />} items={location.content.npcs} />
                    <GameSection title="Resursai" icon={<TreePine size={18} />} items={location.content.resources} onClick={(i) => location.content.resources[i].action === 'chop' && busy('/game/actions/chop')} />
                    <GameSection title="Monstrai" icon={<Swords size={18} />} items={location.content.monsters.map(x => ({ ...x, detail: `Combat level ${x.level}` }))} muted />
                </>}

                {tab === 'combat' && <Panel title="Combat"><div className="stats-grid">{['attack','strength','defence','hitpoints','ranged','magic'].map(name => <Stat key={name} name={name} value={skill(name).level} />)}</div><h3>Attack style</h3><div className="choice-row"><button>Accurate</button><button>Aggressive</button><button>Defensive</button></div></Panel>}
                {tab === 'skills' && <Panel title="Skills"><div className="skill-list">{skillNames.map(name => <div className="skill-row" key={name}><span>{name}</span><b>{skill(name).level}</b><small>{skill(name).xp.toLocaleString()} XP</small></div>)}</div></Panel>}
                {tab === 'inventory' && <Panel title={`Inventory · ${inventory.length}/28`}><div className="inventory-grid">{Array.from({ length: 28 }, (_, i) => inventory[i] ? <div className="item-slot filled" key={i}><Axe size={22}/><span>{inventory[i].name}</span><b>{inventory[i].quantity}</b></div> : <div className="item-slot" key={i} />)}</div></Panel>}
                {tab === 'prayer' && <Panel title={`Prayer · ${player.prayerPoints}`}><div className="prayer-list">{['Thick Skin','Burst of Strength','Clarity of Thought','Sharp Eye'].map(x => <button key={x}><Sparkles size={18}/><span>{x}</span><small>Locked</small></button>)}</div></Panel>}
            </main>

            <nav className="bottom-nav">
                <Nav icon={<Map />} label="World" active={tab === 'world'} onClick={() => setTab('world')} />
                <Nav icon={<Swords />} label="Combat" active={tab === 'combat'} onClick={() => setTab('combat')} />
                <Nav icon={<Sparkles />} label="Skills" active={tab === 'skills'} onClick={() => setTab('skills')} />
                <Nav icon={<Backpack />} label="Inventory" active={tab === 'inventory'} onClick={() => setTab('inventory')} />
                <Nav icon={<Shield />} label="Prayer" active={tab === 'prayer'} onClick={() => setTab('prayer')} />
            </nav>
        </div>
    );
}

function GameSection({ title, icon, items, onClick, muted = false }: { title: string; icon: React.ReactNode; items: ContentItem[]; onClick?: (index: number) => void; muted?: boolean }) {
    return <section className="game-section"><h2>{icon}{title}<span>{items.length}</span></h2><div className="action-list">{items.map((item, i) => <button key={item.name} disabled={muted} onClick={() => onClick?.(i)}><span>{item.name}<small>{item.detail}</small></span><ChevronRight size={18}/></button>)}</div></section>;
}
function Panel({ title, children }: { title: string; children: React.ReactNode }) {
 return <section className="panel"><div className="eyebrow">RuneVentures</div><h1>{title}</h1>{children}</section>; 
}
function Stat({ name, value }: { name: string; value: number }) {
 return <div><span>{name}</span><b>{value}</b></div>; 
}
function Nav({ icon, label, active, onClick }: { icon: React.ReactNode; label: string; active: boolean; onClick: () => void }) {
 return <button className={active ? 'active' : ''} onClick={onClick}>{icon}<span>{label}</span></button>; 
}
