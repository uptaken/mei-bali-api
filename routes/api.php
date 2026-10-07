<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderOperationalController;
use App\Http\Controllers\Api\PayableController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TiketController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\DurationController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\BiayaDefaultController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\WAController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\WhatsAppTemplateController;

use App\Http\Controllers\ExportController;

Route::post('/login', [AuthController::class, 'login']);

Route::prefix('wa')->group(function () {

	Route::get('/start', [WAController::class, 'start_session']);
	Route::get('/restart', [WAController::class, 'restart_session']);
	Route::get('/status', [WAController::class, 'status_session']);
	Route::get('/terminate', [WAController::class, 'terminate_session']);
	Route::get('/qr', [WAController::class, 'qr_session']);
	Route::get('/qr/image', [WAController::class, 'qr_image_session']);
	Route::get('/state', [WAController::class, 'get_state']);
	Route::get('/number/search', [WAController::class, 'get_number_search']);

});

Route::prefix('export')->group(function () {
	Route::get('/order/pdf', [ExportController::class, 'order_pdf'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {


    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [AuthController::class, 'updateProfile']);
    Route::patch('/me/password', [AuthController::class, 'updatePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Master data — every authenticated role may read; only Admin/Super Admin may write.
    Route::apiResource('clients', ClientController::class)->except(['store', 'update', 'destroy']);
		Route::apiResource('countries', CountryController::class)->except(['store', 'update', 'destroy']);
		Route::apiResource('packages', PackageController::class)->except(['store', 'update', 'destroy']);
		Route::apiResource('languages', LanguageController::class)->except(['store', 'update', 'destroy']);
		Route::apiResource('durations', DurationController::class)->except(['store', 'update', 'destroy']);
		Route::apiResource('biaya-defaults', BiayaDefaultController::class)->except(['store', 'update', 'destroy']);
		Route::apiResource('guests', GuestController::class)->except(['store', 'update', 'destroy']);
    Route::apiResource('suppliers', SupplierController::class)->except(['store', 'update', 'destroy']);
    Route::apiResource('vehicles', VehicleController::class)->only(['index']);
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    Route::apiResource('product-categories', ProductCategoryController::class)->only(['index', 'show']);
    Route::apiResource('tikets', TiketController::class)->only(['index', 'show']);
    Route::apiResource('whatsapp-templates', WhatsAppTemplateController::class)->only(['index', 'show']);

    Route::middleware('role:Admin,Super Admin')->group(function () {
			Route::apiResource('countries', CountryController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('packages', PackageController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('languages', LanguageController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('durations', DurationController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('guests', GuestController::class)->only(['store', 'update', 'destroy']);
			Route::apiResource('biaya-defaults', BiayaDefaultController::class)->only(['store', 'update', 'destroy']);

        Route::apiResource('clients', ClientController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('suppliers', SupplierController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('vehicles', VehicleController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('product-categories', ProductCategoryController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('whatsapp-templates', WhatsAppTemplateController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('tikets', TiketController::class)->only(['store', 'update', 'destroy']);
    });

    Route::middleware('role:Super Admin')->apiResource('users', UserController::class)->except(['create', 'edit']);

    // Orders — Operator may view; only Admin/Super Admin may create/edit (mirrors canCreateOrder()).
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/send-wa-supplier', [WhatsAppController::class, 'sendSupplierConfirmation']);

    Route::middleware('role:Admin,Super Admin')->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
        Route::patch('/orders/{order}', [OrderController::class, 'update']);
				Route::patch('/orders/lines/{order}', [OrderController::class, 'updateLines']);
        Route::patch('/orders/{order}/operational', [OrderOperationalController::class, 'update']);
        Route::post('/orders/{order}/selesai', [OrderController::class, 'markSelesai']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    });

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::patch('/invoices/{invoice}/lines', [InvoiceController::class, 'updateLines']);
    Route::post('/invoices/{invoice}/mark-billed', [InvoiceController::class, 'markBilled']);
    Route::post('/invoices/{invoice}/unmark-billed', [InvoiceController::class, 'unmarkBilled']);
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment']);

    // Tagihan yang Perlu Dibayarkan (Payables) — always derived, never authored.
    Route::get('/payables', [PayableController::class, 'index']);
    Route::get('/payables/{id}', [PayableController::class, 'show'])->where('id', '.*');
    Route::post('/payables/{id}/mark-paid', [PayableController::class, 'markPaid'])->where('id', '.*');
    Route::post('/payables/mark-paid-bulk', [PayableController::class, 'markPaidBulk']);

    // WhatsApp
    Route::post('/whatsapp/send', [WhatsAppController::class, 'send']);
});
