import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ChevronRight } from 'lucide-react';

type LocationItem = {
    name: string;
    detail?: string;
    action?: string;
    requiredLevel?: number;
    level?: number;
    slug?: string;
    href?: string;
    post?: string;
    buttonLabel?: string;
};

interface Props {
    location: {
        id: number;
        name: string;
        region: string;
    };
    category: {
        key: string;
        label: string;
    };
    items: LocationItem[];
}

const coldbreezeStarterMonsters: LocationItem[] = [
    { name: 'Chicken', slug: 'chicken', level: 1 },
    { name: 'Goblin', slug: 'goblin', level: 2 },
    { name: 'Duck', slug: 'duck', level: 1 },
];

const coldbreezeStarterNpcs: LocationItem[] = [
    {
        name: 'Parduotuvė',
        detail: 'Pardavėjas',
        href: '/game/shop',
        buttonLabel: 'Atidaryti',
    },
    {
        name: 'Naujokų gidas',
        post: '/game/npc/newcomer-guide',
        buttonLabel: 'Kalbėti',
    },
];

export default function LocationCategory({ location, category, items }: Props) {
    let displayItems = items;

    if (location.name === 'Coldbreeze Port' && category.key === 'monsters') {
        displayItems = coldbreezeStarterMonsters;
    }

    if (location.name === 'Coldbreeze Port' && category.key === 'npcs') {
        displayItems = coldbreezeStarterNpcs;
    }

    return (
        <div className="game-shell location-list-page">
            <Head title={`${category.label} · ${location.name} · RuneVentures`} />

            <header className="location-list-header">
                <button type="button" onClick={() => router.visit('/main')} aria-label="Grįžti">
                    <ArrowLeft size={20} />
                </button>
                <div>
                    <span>{location.region}</span>
                    <strong>{location.name}</strong>
                </div>
            </header>

            <main>
                <div className="location-list-title">
                    <span>Lokacija <strong>{location.name}</strong></span>
                    <h1>{category.label}</h1>
                </div>

                <div className="location-list">
                    {displayItems.length === 0 && (
                        <div className="location-list-empty">Šioje vietovėje įrašų dar nėra.</div>
                    )}

                    {displayItems.map((item, index) => {
                        if (category.key === 'monsters' && item.slug) {
                            return (
                                <div className="location-list-row monster-list-row" key={`${item.name}-${index}`}>
                                    <div className="monster-list-copy">
                                        <strong>{item.name}</strong>
                                        <span>Level {item.level ?? 1}</span>
                                    </div>
                                    <button
                                        className="monster-fight-button"
                                        type="button"
                                        onClick={() => router.post(`/game/actions/attack/${item.slug}`)}
                                    >
                                        Fight
                                    </button>
                                </div>
                            );
                        }

                        const action = item.href || item.post;
                        if (action) {
                            return (
                                <div className="location-list-row npc-action-row" key={`${item.name}-${index}`}>
                                    <div>
                                        <strong>{item.name}</strong>
                                        {item.detail && <span>{item.detail}</span>}
                                    </div>
                                    <button
                                        className="monster-fight-button"
                                        type="button"
                                        onClick={() => {
                                            if (item.href) {
                                                router.visit(item.href);
                                            } else if (item.post) {
                                                router.post(item.post);
                                            }
                                        }}
                                    >
                                        {item.buttonLabel ?? 'Atidaryti'}
                                    </button>
                                </div>
                            );
                        }

                        return (
                            <div className="location-list-row" key={`${item.name}-${index}`}>
                                <div>
                                    <strong>{item.name}</strong>
                                    {item.detail && <span>{item.detail}</span>}
                                    {item.requiredLevel && <span>Reikia lygio {item.requiredLevel}</span>}
                                </div>
                                <ChevronRight size={17} />
                            </div>
                        );
                    })}
                </div>
            </main>
        </div>
    );
}
