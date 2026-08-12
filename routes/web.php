<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [GameController::class, 'show'])->name('dashboard');
    Route::post('game/travel/{location}', [GameController::class, 'travel'])->name('game.travel');
    Route::post('game/actions/chop', [GameController::class, 'chop'])->name('game.chop');
    Route::post('game/actions/attack/{monster}', [GameController::class, 'attack'])->name('game.attack');
});

require __DIR__.'/settings.php';
