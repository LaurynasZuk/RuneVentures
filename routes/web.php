<?php

use App\Http\Controllers\CombatController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('main')
        : redirect()->route('login');
})->name('home');

// Backward-compatible alias for starter-kit frontend references and old bookmarks.
Route::redirect('dashboard', '/main', 301)->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('main', [GameController::class, 'show'])->name('main');
    Route::get('game/location/{location}/{category}', [GameController::class, 'locationCategory'])
        ->name('game.location.category');
    Route::post('game/travel/{location}', [GameController::class, 'travel'])->name('game.travel');
    Route::post('game/actions/chop', [GameController::class, 'chop'])->name('game.chop');

    Route::post('game/actions/attack/{monster}', [CombatController::class, 'start'])->name('game.attack');
    Route::get('game/combat', [CombatController::class, 'show'])->name('game.combat');
    Route::get('game/combat/state', [CombatController::class, 'state'])->name('game.combat.state');
    Route::post('game/combat/leave', [CombatController::class, 'leave'])->name('game.combat.leave');
});

require __DIR__.'/settings.php';
