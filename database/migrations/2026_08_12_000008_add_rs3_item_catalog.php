<?php

use App\Support\GameItemCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->unsignedSmallInteger('tier')->nullable();
            $table->boolean('f2p')->default(true);
            $table->string('required_skill')->nullable();
            $table->unsignedSmallInteger('required_level')->nullable();
            $table->json('game_data')->nullable();

            $table->index('category');
            $table->index(['f2p', 'category']);
        });

        $now = now();

        foreach (GameItemCatalog::all() as $item) {
            $slug = $item['slug'];
            $exists = DB::table('items')->where('slug', $slug)->exists();
            $payload = $item;
            unset($payload['slug']);

            $payload['game_data'] = json_encode(
                $payload['game_data'],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
            $payload['updated_at'] = $now;

            if (! $exists) {
                $payload['created_at'] = $now;
            }

            DB::table('items')->updateOrInsert(
                ['slug' => $slug],
                $payload,
            );
        }
    }

    public function down(): void
    {
        $catalogSlugs = collect(GameItemCatalog::all())->pluck('slug')->all();

        DB::table('items')
            ->whereIn('slug', $catalogSlugs)
            ->whereNotIn('slug', ['bones', 'logs', 'bronze-sq-shield'])
            ->delete();

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['f2p', 'category']);
            $table->dropColumn([
                'category',
                'tier',
                'f2p',
                'required_skill',
                'required_level',
                'game_data',
            ]);
        });
    }
};
