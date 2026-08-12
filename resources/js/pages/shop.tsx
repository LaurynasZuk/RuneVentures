import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Coins, Package, Store } from 'lucide-react';
import { useState } from 'react';

type ShopTab = 'buy' | 'sell';

type ShopItem = {
    id: number;
    name: string;
    icon: string;
    stock: number;
    price: number;
};

type InventoryItem = {
    slot: number;
    name: string;
    icon: string;
    quantity: number;
    price: number;
    sellable: boolean;
};

interface Props {
    shop: {
        name: string;
        keeper: string;
    };
    coins: number;
    stock: ShopItem[];
    inventory: InventoryItem[];
    flash: { game?: string };
}

export default function Shop({ shop, coins, stock, inventory, flash }: Props) {
    const [tab, setTab] = useState<ShopTab>('buy');

    return (
        <div className="game-shell shop-page">
            <Head title={`${shop.name} · RuneVentures`} />

            <header className="shop-header">
                <button type="button" onClick={() => router.visit('/main')} aria-label="Grįžti">
                    <ArrowLeft size={20} />
                </button>
                <div>
                    <span>Coldbreeze Port</span>
                    <strong>{shop.name}</strong>
                </div>
                <div className="shop-coins">
                    <Coins size={16} />
                    <strong>{coins}</strong>
                </div>
            </header>

            <main>
                {flash.game && <div className="game-toast">{flash.game}</div>}

                <section className="shop-keeper-card">
                    <Store size={22} />
                    <div>
                        <span>Pardavėjas</span>
                        <strong>{shop.keeper}</strong>
                    </div>
                </section>

                <div className="shop-tabs" role="tablist" aria-label="Parduotuvės režimas">
                    <button
                        type="button"
                        className={tab === 'buy' ? 'active' : ''}
                        role="tab"
                        aria-selected={tab === 'buy'}
                        onClick={() => setTab('buy')}
                    >
                        Buy
                    </button>
                    <button
                        type="button"
                        className={tab === 'sell' ? 'active' : ''}
                        role="tab"
                        aria-selected={tab === 'sell'}
                        onClick={() => setTab('sell')}
                    >
                        Sell
                    </button>
                </div>

                {tab === 'buy' && (
                    <section className="shop-section shop-tab-panel" role="tabpanel">
                        <div className="shop-section-title">
                            <strong>Pirkti</strong>
                            <span>Pardavėjo prekės</span>
                        </div>

                        <div className="shop-list">
                            {stock.length === 0 && (
                                <div className="shop-empty">Pardavėjas šiuo metu neturi prekių.</div>
                            )}

                            {stock.map((item) => (
                                <div className="shop-row" key={item.id}>
                                    <span className="shop-item-icon"><Package size={18} /></span>
                                    <div className="shop-item-copy">
                                        <strong>{item.name}</strong>
                                        <span>Stock: {item.stock}</span>
                                    </div>
                                    <button
                                        type="button"
                                        disabled={item.stock <= 0 || coins < item.price}
                                        onClick={() => router.post(`/game/shop/buy/${item.id}`, {}, {
                                            preserveScroll: true,
                                            preserveState: true,
                                        })}
                                    >
                                        {item.price} gp
                                    </button>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {tab === 'sell' && (
                    <section className="shop-section shop-tab-panel" role="tabpanel">
                        <div className="shop-section-title">
                            <strong>Parduoti</strong>
                            <span>Daiktai iš inventoriaus</span>
                        </div>

                        <div className="shop-list">
                            {inventory.length === 0 && (
                                <div className="shop-empty">Nėra daiktų, kuriuos galėtum parduoti.</div>
                            )}

                            {inventory.map((item) => (
                                <div className="shop-row" key={item.slot}>
                                    <span className="shop-item-icon"><Package size={18} /></span>
                                    <div className="shop-item-copy">
                                        <strong>{item.name}</strong>
                                        <span>Kiekis: {item.quantity}</span>
                                    </div>
                                    <button
                                        type="button"
                                        disabled={!item.sellable}
                                        onClick={() => router.post(`/game/shop/sell/${item.slot}`, {}, {
                                            preserveScroll: true,
                                            preserveState: true,
                                        })}
                                    >
                                        {item.sellable ? `+${item.price} gp` : '0 gp'}
                                    </button>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </main>
        </div>
    );
}
