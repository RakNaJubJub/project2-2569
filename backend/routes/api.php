<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoomController;
use App\Http\Middleware\VerifyRoomSession;
use App\Http\Controllers\Api\LocationController;

Route::post('/rooms', [RoomController::class, 'store']);

Route::post('/rooms/join', [RoomController::class, 'join'])
    ->middleware('throttle:10,1');

Route::get('/rooms/{pin}/members', [RoomController::class, 'members'])
    ->middleware(VerifyRoomSession::class)
    ->where('pin', '[0-9]{6}')
    ->name('rooms.members');

Route::post('/locations', [LocationController::class, 'store'])
    ->middleware(VerifyRoomSession::class)
    ->name('locations.store');

Route::get('/rooms/{pin}/status', [RoomController::class, 'status'])
    ->middleware(VerifyRoomSession::class)
    ->where('pin', '[0-9]{6}')
    ->name('rooms.status');

Route::post('/rooms/{pin}/start', [RoomController::class, 'startFinding'])
    ->middleware(VerifyRoomSession::class)
    ->where('pin', '[0-9]{6}')
    ->name('rooms.start');