<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminOldJewelleryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\FastrrCatalogController;
use App\Http\Controllers\Api\FastrrWebhookController;
use App\Http\Controllers\Api\OldJewelleryRequestController;
use App\Http\Controllers\Api\SellRequestController;
use App\Http\Controllers\Api\VendorOldJewelleryController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile REST API — stateless, bearer-token auth (self-contained TokenGuard,
| see app/Auth/TokenGuard.php + app/Models/PersonalAccessToken.php).
|
| Consistent JSON shapes everywhere (App\Http\Api\ApiResponses trait):
|   success => { "data": ... }
|   error   => { "success": false, "message": "..." }  with HTTP status
|               401 unauthenticated / 403 forbidden / 404 not found / 422 validation
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| v1 — Shiprocket Fastrr checkout, old-jewellery buy-back and wallet
| (Sanctum auth). These predate the mobile API below and are still called by
| Fastrr and the vendor bidding flow, so they must stay registered.
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // Shiprocket Fastrr Checkout reads our catalogue here and posts each
    // completed order back. Save these URLs under Dashboard → Custom
    // Endpoints; every call must carry the HMAC or FASTRR_CATALOG_TOKEN.
    Route::prefix('fastrr')->middleware(['fastrr', 'throttle:120,1'])->group(function () {
        Route::get('/products', [FastrrCatalogController::class, 'products']);
        Route::get('/collections', [FastrrCatalogController::class, 'collections']);
        Route::get('/collections/{collection}/products', [FastrrCatalogController::class, 'collectionProducts']);
        Route::post('/webhooks/order', [FastrrWebhookController::class, 'order']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/old-jewellery/requests', [OldJewelleryRequestController::class, 'store'])
            ->middleware('throttle:10,60');
        Route::get('/old-jewellery/requests', [OldJewelleryRequestController::class, 'index']);
        Route::get('/old-jewellery/requests/{oldJewelleryRequest:request_number}', [OldJewelleryRequestController::class, 'show']);
        Route::get('/old-jewellery/requests/{oldJewelleryRequest:request_number}/status', [OldJewelleryRequestController::class, 'status']);

        Route::get('/wallet', [WalletController::class, 'show']);
        Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
        Route::get('/wallet/old-jewellery-credits', [WalletController::class, 'oldJewelleryCredits']);

        Route::prefix('admin/old-jewellery')->group(function () {
            Route::get('/', [AdminOldJewelleryController::class, 'index']);
            Route::get('/{oldJewelleryRequest:request_number}', [AdminOldJewelleryController::class, 'show']);
            Route::get('/{oldJewelleryRequest:request_number}/bids', [AdminOldJewelleryController::class, 'bids']);
            Route::post('/{oldJewelleryRequest:request_number}/bid', [AdminOldJewelleryController::class, 'bid']);
            Route::post('/{oldJewelleryRequest:request_number}/close', [AdminOldJewelleryController::class, 'close']);
        });
    });

    Route::prefix('vendor/old-jewellery/{token}')
        ->middleware(['vendor.token', 'throttle:30,1'])
        ->group(function () {
            Route::get('/', [VendorOldJewelleryController::class, 'show']);
            Route::post('/accept', [VendorOldJewelleryController::class, 'accept']);
            Route::post('/decline', [VendorOldJewelleryController::class, 'decline']);
        });
});

Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'estele-api', 'time' => now()->toIso8601String()]));

/*
|--------------------------------------------------------------------------
| Home & Catalog (public)
|--------------------------------------------------------------------------
*/
Route::get('/home', [CatalogController::class, 'home']);
Route::get('/categories', [CatalogController::class, 'categories']);
Route::get('/categories/{slug}/products', [CatalogController::class, 'categoryProducts']);
Route::get('/collections', [CatalogController::class, 'collections']);
Route::get('/collections/{slug}/products', [CatalogController::class, 'collectionProducts']);
Route::get('/products/{slug}', [CatalogController::class, 'productShow']);
Route::get('/search', [CatalogController::class, 'search']);
Route::get('/search/suggest', [CatalogController::class, 'suggest']);
Route::get('/wishlist', [ContentController::class, 'wishlist']);

/*
|--------------------------------------------------------------------------
| Auth (public)
|--------------------------------------------------------------------------
*/
Route::prefix('register')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendRegisterOtp'])
        ->middleware('throttle:5,1');
    Route::post('/verify-otp', [AuthController::class, 'verifyRegisterOtp'])
        ->middleware('throttle:10,1');
    Route::post('/email/send-otp', [AuthController::class, 'sendRegisterEmailOtp'])
        ->middleware('throttle:5,1');
    Route::post('/email/verify-otp', [AuthController::class, 'verifyRegisterEmailOtp'])
        ->middleware('throttle:10,1');
    Route::post('/', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');
});

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:8,1');

Route::prefix('login/mobile')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendLoginOtp'])
        ->middleware('throttle:5,1');
    Route::post('/verify-otp', [AuthController::class, 'verifyLoginOtp'])
        ->middleware('throttle:10,1');
});

Route::prefix('login/email')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendLoginEmailOtp'])
        ->middleware('throttle:5,1');
    Route::post('/verify-otp', [AuthController::class, 'verifyLoginEmailOtp'])
        ->middleware('throttle:10,1');
});

// Website-parity mobile auth: ONE OTP round serves both login and registration,
// exactly like the website's OtpAuthController (issue for any phone, then branch
// on verify — existing number logs in, new number gets a registration nonce).
Route::prefix('auth/mobile')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendMobileAuthOtp'])
        ->middleware('throttle:5,1');
    Route::post('/verify-otp', [AuthController::class, 'verifyMobileAuthOtp'])
        ->middleware('throttle:10,1');
});

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:5,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| Cart (guest via X-Cart-Token, user via bearer token)
|--------------------------------------------------------------------------
*/
Route::get('/cart', [CartController::class, 'index']);
Route::delete('/cart', [CartController::class, 'clear']);
Route::post('/cart/coupon', [CartController::class, 'applyCoupon']);
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon']);
Route::post('/cart/{product:slug}', [CartController::class, 'store']);
Route::patch('/cart/items/{cartItem}', [CartController::class, 'update']);
Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy']);

/*
|--------------------------------------------------------------------------
| Content (public)
|--------------------------------------------------------------------------
*/
Route::get('/blogs', [ContentController::class, 'blogs']);
Route::get('/blogs/{slug}', [ContentController::class, 'blogShow']);
Route::get('/faq', [ContentController::class, 'faq']);
Route::get('/pages/{slug}', [ContentController::class, 'page']);
Route::get('/stores', [ContentController::class, 'stores']);
Route::post('/newsletter/subscribe', [ContentController::class, 'newsletterSubscribe'])
    ->middleware('throttle:10,60');

/*
|--------------------------------------------------------------------------
| Checkout & payment
|--------------------------------------------------------------------------
*/
Route::get('/checkout/pincode/{postalCode}', [CheckoutController::class, 'pincodeLookup'])
    ->where('postalCode', '[0-9]{6}');

Route::post('/payment/callback/{orderNumber}', [CheckoutController::class, 'paymentCallback']);

// Razorpay server-to-server webhook (signature-verified, no bearer token).
Route::post('/webhooks/razorpay', [PaymentController::class, 'webhook'])
    ->name('api.webhooks.razorpay')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

/*
|--------------------------------------------------------------------------
| Authenticated (bearer token required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api-token')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/account', [AccountController::class, 'index']);

    Route::prefix('account')->group(function () {
        Route::patch('/profile', [AccountController::class, 'updateProfile']);
        Route::patch('/password', [AccountController::class, 'updatePassword']);

        Route::get('/orders', [AccountController::class, 'orders']);
        Route::get('/orders/{orderNumber}', [AccountController::class, 'orderShow']);
        Route::get('/orders/{orderNumber}/invoice', [AccountController::class, 'orderInvoice']);
        Route::post('/orders/{orderNumber}/cancellation-request', [AccountController::class, 'requestCancellation']);

        Route::get('/wallet/transactions', [AccountController::class, 'walletTransactions']);

        // Old Jewellery buy-back.
        Route::get('/sell/requests', [SellRequestController::class, 'index']);
        Route::post('/sell/requests', [SellRequestController::class, 'store'])
            ->middleware('throttle:5,60');
        Route::get('/sell/requests/{requestNumber}', [SellRequestController::class, 'show']);
        Route::post('/sell/requests/{requestNumber}/cancel', [SellRequestController::class, 'cancel']);

        Route::get('/addresses', [AccountController::class, 'addresses']);
        Route::post('/addresses', [AccountController::class, 'addressStore']);
        Route::patch('/addresses/{id}', [AccountController::class, 'addressUpdate']);
        Route::delete('/addresses/{id}', [AccountController::class, 'addressDestroy']);
    });

    // Reviews require a verified user (bearer token). This was previously in the
    // public block, causing mobile review submissions to always 401.
    Route::post('/products/{product:slug}/reviews', [ContentController::class, 'storeReview'])
        ->middleware('throttle:5,60');

    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::post('/payment/{orderNumber}/retry', [CheckoutController::class, 'retry'])
        ->middleware('throttle:20,1');
});