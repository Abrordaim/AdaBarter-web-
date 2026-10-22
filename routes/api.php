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
use App\Http\Controllers\Api\MonetizationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\PaymentWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes for AdaBarter Mobile Application
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Payment Gateway Webhook (Midtrans notifications, protected by signature verification)
Route::post('/payments/midtrans/notification', [PaymentWebhookController::class, 'handle']);

// Region routes (Indonesian geographic hierarchy)
Route::get('/regions/provinces', [RegionController::class, 'provinces']);
Route::get('/regions/provinces/{provinceId}/regencies', [RegionController::class, 'regencies']);
Route::get('/regions/regencies/{regencyId}/districts', [RegionController::class, 'districts']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/items', [ItemController::class, 'index']);
Route::get('/items/cities', [ItemController::class, 'cities']); // Must be before /items/{id}
Route::get('/items/{id}', [ItemController::class, 'show']);
Route::get('/banners', [BannerController::class, 'index']);
Route::get('/monetization/plans', [MonetizationController::class, 'plans']);
Route::get('/users/{id}/ratings', [RatingController::class, 'userRatings']);
Route::get('/users/{id}', [UserController::class, 'show']);

// Protected routes (Sanctum Token required)
// check_banned: blocks API access if user account has been banned by admin
Route::middleware(['auth:sanctum', 'check_banned'])->group(function () {
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
    Route::post('/offers/{id}/pay', [OfferController::class, 'pay']);
    Route::post('/offers/{id}/confirm', [OfferController::class, 'confirm']);
    Route::post('/offers/{id}/dispute', [OfferController::class, 'dispute']);

    // Chats (Negotiation room tied to matched offers)
    Route::get('/chats', [ChatController::class, 'conversations']);
    Route::get('/offers/{offerId}/chats', [ChatController::class, 'messages']);
    Route::post('/offers/{offerId}/chats', [ChatController::class, 'send']);

    // Vouchers (Claim & List)
    Route::get('/vouchers/available', [VoucherController::class, 'available']);
    Route::get('/vouchers/my', [VoucherController::class, 'myVouchers']);
    Route::post('/vouchers/claim', [VoucherController::class, 'claim']);

    // Monetization (VIP, Boost, Quota Purchase, Transactions)
    Route::post('/monetization/subscribe', [MonetizationController::class, 'subscribe']);
    Route::post('/monetization/items/{id}/boost', [MonetizationController::class, 'boost']);
    Route::post('/monetization/quota/purchase', [MonetizationController::class, 'purchaseQuota']);
    Route::get('/monetization/transactions', [MonetizationController::class, 'transactions']);

    // Wallet & Withdrawal
    Route::get('/wallet', [\App\Http\Controllers\Api\WalletController::class, 'index']);
    Route::post('/wallet/withdraw', [\App\Http\Controllers\Api\WalletController::class, 'withdraw']);
    Route::get('/wallet/withdrawals', [\App\Http\Controllers\Api\WalletController::class, 'withdrawals']);

    // Reports & Complaints
    Route::post('/reports', [ReportController::class, 'store']);

    // Ratings & Reviews
    Route::post('/ratings', [RatingController::class, 'store']);
});
