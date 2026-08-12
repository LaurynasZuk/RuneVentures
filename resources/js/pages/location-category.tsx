import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ChevronRight } from 'lucide-react';

type LocationItem = {
    name: string;
    detail?: string;
    action?: string;
    requiredLevel?: number;
    level?: number;
    slug?: string;
    xp?: number;
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

export default function LocationCategory({ location, category, items }: Props) {
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
                    {items.length === 0 && (
                        <div className="location-list-empty">Šioje vietovėje įrašų dar nėra.</div>
                    )}

                    {items.map((item, index) => {
                        const content = (
                            <>
                                <div>
                                    <strong>{item.name}</strong>
                                    {item.detail && <span>{item.detail}</span>}
                                    {item.requiredLevel && <span>Reikia lygio {item.requiredLevel}</span>}
                                    {item.level && <span>Combat {item.level}{item.xp ? ` · ${item.xp} XP` : ''}</span>}
                                </div>
                                <ChevronRight size={17} />
                            </>
                        );

                        if (category.key === 'monsters' && item.slug) {
                            return (
                                <button
                                    className="location-list-row"
                                    type="button"
                                    key={`${item.name}-${index}`}
                                    onClick={() => router.post(`/game/actions/attack/${item.slug}`)}
                                >
                                    {content}
                                </button>
                            );
                        }

                        return (
                            <div className="location-list-row" key={`${item.name}-${index}`}>
                                {content}
                            </div>
                        );
                    })}
                </div>
            </main>
        </div>
    );
}
