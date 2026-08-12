<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Player;
use App\Models\ShopStock;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    private const SHOP_KEY = 'coldbreeze-general';

    private const DEFAULT_STOCK = [
        ['slug' => 'pot', 'name' => 'Pot', 'icon' => 'pot', 'stock' => 5, 'sell' => 1, 'buy' => 0, 'value' => 1],
        ['slug' => 'shears', 'name' => 'Shears', 'icon' => 'tool', 'stock' => 2, 'sell' => 1, 'buy' => 0, 'value' => 1],
        ['slug' => 'bucket', 'name' => 'Bucket', 'icon' => 'bucket', 'stock' => 3, 'sell' => 2, 'buy' => 0, 'value' => 2],
        ['slug' => 'bowl', 'name' => 'Bowl', 'icon' => 'bowl', 'stock' => 2, 'sell' => 5, 'buy' => 1, 'value' => 4],
        ['slug' => 'tinderbox', 'name' => 'Tinderbox', 'icon' => 'tinderbox', 'stock' => 2, 'sell' => 1, 'buy' => 0, 'value' => 1],
        ['slug' => 'chisel', 'name' => 'Chisel', 'icon' => 'chisel', 'stock' => 2, 'sell' => 1, 'buy' => 0, 'value' => 1],
        ['slug' => 'hammer', 'name' => 'Hammer', 'icon' => 'hammer', 'stock' => 5, 'sell' => 1, 'buy' => 0, 'value' => 1],
    ];

    private const REMOVED_STOCK_SLUGS = [
        'security-book',
        'newcomer-map',
        'cake-tin',
        'empty-jug-pack',
        'jug',
    ];

    public function __construct(private readonly InventoryService $inventory)
    {
    }

    public function show(Request $request): Response
    {
        $player = $this->player($request);
        $this->ensureDefaultStock();
        $player->load(['inventory.item', 'backpacks.item']);

        $stock = ShopStock::query()
            ->with('item')
            ->where('shop_key', self::SHOP_KEY)
            ->where('stock', '>', 0)
            ->orderBy('id')
            ->get();

        $stockByItem = $stock->keyBy('item_id');

        return Inertia::render('shop', [
            'shop' => [
                'name' => 'Parduotuvė',
                'keeper' => 'Pardavėjas',
            ],
            'coins' => $this->inventory->coins($player),
            'stock' => $stock->map(fn (ShopStock $row) => [
                'id' => $row->id,
                'name' => $row->item->name,
                'icon' => $row->item->icon,
                'stock' => $row->stock,
                'price' => $row->sell_price,
            ])->values(),
            'inventory' => $player->inventory
                ->reject(fn (InventoryItem $stack) => $stack->item->slug === 'coins')
                ->map(function (InventoryItem $stack) use ($stockByItem) {
                    $removedFromShop = in_array($stack->item->slug, self::REMOVED_STOCK_SLUGS, true);
                    $shopRow = $stockByItem->get($stack->item_id);
                    $sellPrice = $removedFromShop
                        ? 0
                        : ($shopRow
                            ? $shopRow->buy_price
                            : $this->generalStoreBuyPrice($stack->item));

                    return [
                        'slot' => $stack->slot,
                        'name' => $stack->item->name,
                        'icon' => $stack->item->icon,
                        'quantity' => $stack->quantity,
                        'price' => $sellPrice,
                        'sellable' => $sellPrice > 0,
                    ];
                })->values(),
            'flash' => ['game' => session('game')],
        ]);
    }

    public function buy(Request $request, ShopStock $stock): RedirectResponse
    {
        $player = $this->player($request);
        abort_unless($stock->shop_key === self::SHOP_KEY, 404);

        $result = DB::transaction(function () use ($player, $stock): string {
            $lockedStock = ShopStock::query()
                ->with('item')
                ->whereKey($stock->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedStock->stock < 1) {
                return 'Šio daikto šiuo metu nėra.';
            }

            if ($this->inventory->coins($player) < $lockedStock->sell_price) {
                return 'Nepakanka Coins.';
            }

            if (! $this->inventory->add($player, $lockedStock->item, 1)) {
                return 'Inventorius pilnas.';
            }

            if (! $this->inventory->removeCoins($player, $lockedStock->sell_price)) {
                throw new \RuntimeException('Coin balance changed during purchase.');
            }

            $lockedStock->decrement('stock');

            return "Nupirkta: {$lockedStock->item->name} · -{$lockedStock->sell_price} Coins";
        });

        return back()->with('game', $result);
    }

    public function sell(Request $request, int $slot): RedirectResponse
    {
        $player = $this->player($request);

        $result = DB::transaction(function () use ($player, $slot): string {
            $stack = InventoryItem::query()
                ->with('item')
                ->where('player_id', $player->id)
                ->where('slot', $slot)
                ->lockForUpdate()
                ->first();

            if (! $stack || $stack->item->slug === 'coins') {
                return 'Daikto parduoti nepavyko.';
            }

            if (in_array($stack->item->slug, self::REMOVED_STOCK_SLUGS, true)) {
                return 'Pardavėjas šio daikto neperka.';
            }

            $stock = ShopStock::query()
                ->where('shop_key', self::SHOP_KEY)
                ->where('item_id', $stack->item_id)
                ->lockForUpdate()
                ->first();

            $price = $stock?->buy_price ?? $this->generalStoreBuyPrice($stack->item);
            if ($price <= 0) {
                return 'Pardavėjas už šį daiktą Coins nesiūlo.';
            }

            if ($stack->quantity <= 1) {
                $stack->delete();
            } else {
                $stack->decrement('quantity');
            }

            if (! $this->inventory->addCoins($player, $price)) {
                throw new \RuntimeException('Could not add shop coins to inventory.');
            }

            if (! $stock) {
                $value = max(1, (int) ($stack->item->game_data['value'] ?? 1));
                $stock = ShopStock::create([
                    'shop_key' => self::SHOP_KEY,
                    'item_id' => $stack->item_id,
                    'stock' => 0,
                    'default_stock' => 0,
                    'sell_price' => max(1, (int) floor($value * 1.3)),
                    'buy_price' => $price,
                ]);
            }

            $stock->increment('stock');

            return "Parduota: {$stack->item->name} · +{$price} Coins";
        });

        return back()->with('game', $result);
    }

    private function player(Request $request): Player
    {
        $player = Player::query()
            ->with(['location', 'backpacks.item'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($player->location?->slug === 'starter-village', 403);

        return $player;
    }

    private function ensureDefaultStock(): void
    {
        $removedItemIds = Item::query()
            ->whereIn('slug', self::REMOVED_STOCK_SLUGS)
            ->pluck('id');

        if ($removedItemIds->isNotEmpty()) {
            ShopStock::query()
                ->where('shop_key', self::SHOP_KEY)
                ->whereIn('item_id', $removedItemIds)
                ->delete();
        }

        foreach (self::DEFAULT_STOCK as $definition) {
            $item = Item::firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'icon' => $definition['icon'],
                    'category' => 'general-store',
                    'f2p' => true,
                    'stackable' => false,
                    'stack_limit' => 1,
                ],
            );

            $data = $item->game_data ?? [];
            $data['source_system'] = 'osrs';
            $data['value'] = $definition['value'];
            $data['tradeable'] = true;

            $item->update([
                'name' => $definition['name'],
                'icon' => $definition['icon'],
                'category' => $item->category ?: 'general-store',
                'f2p' => true,
                'game_data' => $data,
            ]);

            $row = ShopStock::firstOrCreate(
                [
                    'shop_key' => self::SHOP_KEY,
                    'item_id' => $item->id,
                ],
                [
                    'stock' => $definition['stock'],
                    'default_stock' => $definition['stock'],
                    'sell_price' => $definition['sell'],
                    'buy_price' => $definition['buy'],
                ],
            );

            $row->update([
                'default_stock' => $definition['stock'],
                'sell_price' => $definition['sell'],
                'buy_price' => $definition['buy'],
            ]);
        }
    }

    private function generalStoreBuyPrice(Item $item): int
    {
        $data = $item->game_data ?? [];
        if (($data['tradeable'] ?? true) === false) {
            return 0;
        }

        $value = (int) ($data['value'] ?? 0);

        return $value > 0 ? (int) floor($value * 0.4) : 0;
    }
}
