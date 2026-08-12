<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [GameController::class, 'show'])->name('dashboard');
    Route::get('game/location/{location}/{category}', [GameController::class, 'locationCategory'])
        ->name('game.location.category');
    Route::post('game/travel/{location}', [GameController::class, 'travel'])->name('game.travel');
    Route::post('game/actions/chop', [GameController::class, 'chop'])->name('game.chop');
    Route::post('game/actions/attack/{monster}', [GameController::class, 'attack'])->name('game.attack');
});

require __DIR__.'/settings.php';
