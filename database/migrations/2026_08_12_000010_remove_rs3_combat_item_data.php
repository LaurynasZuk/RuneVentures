<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // RuneVentures combat follows the OSRS-style roll system only.
        // RS3 dual-wield items are therefore not part of the item catalogue.
        DB::table('items')
            ->where('category', 'weapon')
            ->where('slug', 'like', '%-off-hand-%')
            ->delete();

        DB::table('items')
            ->whereNotNull('game_data')
            ->orderBy('id')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    $data = $this->decodeGameData($item->game_data);

                    foreach (array_keys($data) as $key) {
                        if (str_starts_with($key, 'rs3_')) {
                            unset($data[$key]);
                        }
                    }

                    if (in_array($item->category, ['weapon', 'armour'], true)) {
                        unset($data['source_system'], $data['armour_type']);
                    }

                    DB::table('items')->where('id', $item->id)->update([
                        'game_data' => $this->encodeGameData($data),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // RS3 combat metadata is intentionally not restored.
    }

    private function decodeGameData(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function encodeGameData(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }
};
