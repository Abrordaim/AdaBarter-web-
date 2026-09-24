<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\Api\BannerController;

/*
|--------------------------------------------------------------------------
| API Routes for AdaBarter Mobile Application
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/items', [ItemController::class, 'index']);
Route::get('/items/{id}', [ItemController::class, 'show']);
Route::get('/banners', [BannerController::class, 'index']);

// Protected routes (Sanctum Token required)
Route::middleware(['auth:sanctum'])->group(function () { 
    // Auth & Profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/profile', [UserController::class, 'updateProfile']); // Support multipart/form-data with POST

    // Items (User items & CRUD)
    Route::get('/my-items', [ItemController::class, 'myItems']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::put('/items/{id}', [ItemController::class, 'update']);
    Route::post('/items/{id}', [ItemController::class, 'update']); // Support multipart/form-data update
    Route::delete('/items/{id}', [ItemController::class, 'destroy']);

    // Offers (Barter & Tukar Tambah)
    Route::get('/offers', [OfferController::class, 'index']);
    Route::post('/offers', [OfferController::class, 'store']);
    Route::get('/offers/{id}', [OfferController::class, 'show']);
    Route::post('/offers/{id}/accept', [OfferController::class, 'accept']);
    Route::post('/offers/{id}/reject', [OfferController::class, 'reject']);
    Route::post('/offers/{id}/complete', [OfferController::class, 'complete']);
    Route::post('/offers/{id}/cancel', [OfferController::class, 'cancel']);

    // Chats (Negotiation room tied to matched offers)
    Route::get('/chats', [ChatController::class, 'conversations']);
    Route::get('/offers/{offerId}/chats', [ChatController::class, 'messages']);
    Route::post('/offers/{offerId}/chats', [ChatController::class, 'send']);

    // Vouchers (Claim quota)
    Route::post('/vouchers/claim', [VoucherController::class, 'claim']);
});
