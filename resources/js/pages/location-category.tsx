import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ChevronRight } from 'lucide-react';

type LocationItem = {
    name: string;
    detail?: string;
    action?: string;
    requiredLevel?: number;
    level?: number;
    slug?: string;
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

export default function LocationCategory({ location, category, items }: Props) {
    const displayItems = category.key === 'monsters' && location.name === 'Coldbreeze Port'
        ? coldbreezeStarterMonsters
        : items;

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
