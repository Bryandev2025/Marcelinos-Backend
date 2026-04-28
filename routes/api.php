<?php

use App\Http\Controllers\API\BlockedDateController;
use App\Http\Controllers\API\BlogPostController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\BubbleChatFaqController;
use App\Http\Controllers\API\ChangePasswordController;
use App\Http\Controllers\API\ClientErrorReportController;
use App\Http\Controllers\API\ContactController;
use App\Http\Controllers\API\ForgotPasswordController;
use App\Http\Controllers\API\GalleryController;
use App\Http\Controllers\API\MaintenanceModeController;
use App\Http\Controllers\API\PaymentSettingsController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\RoomDamageLossChargesController;
use App\Http\Controllers\API\RoomController;
use App\Http\Controllers\API\ResetPasswordController;
use App\Http\Controllers\API\VenueController;
use App\Http\Controllers\API\XenditWebhookController;
use App\Http\Middleware\EnsureApiKeyIsValid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/** Public ingest for browser error reports (Slack when SLACK_ERROR_ALERTS_ENABLED=true). */
Route::post('/client-errors', [ClientErrorReportController::class, 'store'])
    ->middleware('throttle:client_errors');

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        return response()->json(['status' => 'ok', 'database' => 'connected'], 200);
    } catch (Throwable $e) {
        return response()->json(['status' => 'error', 'database' => 'disconnected'], 503);
    }
});

Route::get('/maintenance-mode', [MaintenanceModeController::class, 'show']);
Route::get('/payment-settings', [PaymentSettingsController::class, 'show']);
Route::get('/room-damage-loss-charges', [RoomDamageLossChargesController::class, 'show']);
Route::post('/xendit/webhook', [XenditWebhookController::class, 'handle']);

Route::get('bookings/verify-email/{booking}', [BookingController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:receipt_lookup'])
    ->name('bookings.verify-email');

Route::middleware([EnsureApiKeyIsValid::class])->group(function () {
    Route::middleware('throttle:api')->group(function () {
        // Password management (client app)
        Route::post('/auth/forgot-password', [ForgotPasswordController::class, 'store'])
            ->middleware('throttle:password_reset');
        Route::post('/auth/reset-password', [ResetPasswordController::class, 'store'])
            ->middleware('throttle:password_reset');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/auth/change-password', [ChangePasswordController::class, 'store'])
                ->middleware('throttle:password_change');
        });

        // Admin/staff booking management (requires user auth + policy checks)
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('bookings', [BookingController::class, 'index']);
            Route::get('bookings/{id}', [BookingController::class, 'show']);
            Route::put('bookings/{id}', [BookingController::class, 'update']);
            Route::delete('bookings/{id}', [BookingController::class, 'destroy']);
        });

        // Public booking flow (stricter limit on create and review)
        Route::post('bookings', [BookingController::class, 'store'])->middleware('throttle:bookings');
        Route::post('/bookings/{booking:reference_number}/otp/send', [BookingController::class, 'sendBookingOtp'])
            ->middleware('throttle:booking_otp');
        Route::patch('/bookings/{booking:reference_number}/cancel', [BookingController::class, 'cancel']);
        Route::get('/bookings/{booking:reference_number}/billing-statement/pdf', [BookingController::class, 'downloadBillingStatementPdf'])
            ->middleware('throttle:receipt_lookup');
        Route::get('bookings/receipt/{token}', [BookingController::class, 'showByReceiptToken'])->middleware('throttle:receipt_lookup');
        Route::get('bookings/receipt/{token}/payment-status', [BookingController::class, 'paymentStatusByReceiptToken'])->middleware('throttle:receipt_lookup');
        Route::post('bookings/receipt/{token}/retry-payment', [BookingController::class, 'retryOnlinePaymentByReceiptToken'])->middleware('throttle:receipt_lookup');
        Route::post('bookings/receipt/{token}/confirm-payment', [BookingController::class, 'confirmReceiptPayment'])->middleware('throttle:receipt_lookup');
        Route::post('bookings/receipt/{token}/review', [ReviewController::class, 'storeByReceiptToken'])
            ->middleware('throttle:bookings');
        Route::get('bookings/reference/{reference}', [BookingController::class, 'showByReferenceNumber'])->middleware('throttle:receipt_lookup');
        Route::post('bookings/reference/{reference}/review', [ReviewController::class, 'storeByBookingReference'])->middleware('throttle:bookings');
        Route::patch('/bookings/{reference}/reschedule', [BookingController::class, 'reschedule']);
        // Venues
        Route::get('/venues', [VenueController::class, 'index'])->middleware('throttle:catalog_reads');
        Route::get('/venues/{id}', [VenueController::class, 'show']);

        // Rooms
        Route::get('rooms', [RoomController::class, 'index'])->middleware('throttle:catalog_reads');
        Route::get('/rooms/{id}', [RoomController::class, 'show']);

        // Blocked Dates
        Route::get('/blocked-dates', [BlockedDateController::class, 'index'])->middleware('throttle:heavy_availability');

        // Contact form (stricter limit)
        Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact');
        Route::get('/contact/{id}/messages', [ContactController::class, 'messages'])->middleware('throttle:api');
        Route::post('/contact/{id}/messages', [ContactController::class, 'appendMessage'])->middleware('throttle:contact');

        // Gallery
        Route::get('/galleries', [GalleryController::class, 'index']);
        Route::get('/galleries/{id}', [GalleryController::class, 'show']);

        // Blog (Facebook embed posts)
        Route::get('/blog-posts', [BlogPostController::class, 'index']);
        Route::get('/blog-posts/{slug}', [BlogPostController::class, 'show'])
            ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');

        // Bubble chat FAQ
        Route::get('/bubble-chat-faqs', [BubbleChatFaqController::class, 'index']);

        Route::get('/reviews', [ReviewController::class, 'index']);
    });
});
