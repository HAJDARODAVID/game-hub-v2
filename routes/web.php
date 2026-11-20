<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes();

Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/games/{game}', [GameController::class, 'lobby'])->name('games.lobby');
    Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
});
