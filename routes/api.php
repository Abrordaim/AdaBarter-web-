<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', function () {
    return response()->json(['message' => 'Register endpoint']);
});
Route::post('/login', function () {
    return response()->json(['message' => 'Login endpoint']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('items')->group(function () {
        Route::get('/', function () { return response()->json(['message' => 'Items index']); });
    });

    Route::prefix('offers')->group(function () {
        Route::get('/', function () { return response()->json(['message' => 'Offers index']); });
    });

    Route::prefix('chats')->group(function () {
        Route::get('/', function () { return response()->json(['message' => 'Chats index']); });
    });

    Route::prefix('subscriptions')->group(function () {
        Route::get('/', function () { return response()->json(['message' => 'Subscriptions index']); });
    });

    Route::prefix('vouchers')->group(function () {
        Route::get('/', function () { return response()->json(['message' => 'Vouchers index']); });
    });
});
