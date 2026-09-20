<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\BookController as ApiBookController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MetadataController;
use App\Http\Controllers\Api\ReadingDocumentController;
use App\Http\Controllers\Api\ReadingPresenceController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\ResourceCatalogController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ResourceManagementController;
use App\Http\Controllers\SpaController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');
Route::view('/a-propos', 'about')->name('about');

Route::get('/catalogue', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalogue/{resource:slug}/apercu', [CatalogController::class, 'preview'])->middleware('throttle:60,1')->name('resources.preview');
Route::get('/catalogue/{resource:slug}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('/application', SpaController::class)->name('application');
Route::post('/webhooks/{provider}', [WebhookController::class, 'store'])->middleware('throttle:60,1')->name('webhooks.store');

Route::middleware('guest')->group(function () {
    Route::get('/inscription', [AuthController::class, 'create'])->name('register');
    Route::post('/inscription', [AuthController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/connexion', [AuthController::class, 'login'])->name('login');
    Route::post('/connexion', [AuthController::class, 'authenticate'])->middleware('throttle:6,1');
    Route::get('/mot-de-passe/oublie', [AuthController::class, 'forgotPassword'])->name('password.request');
    Route::post('/mot-de-passe/email', [AuthController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/mot-de-passe/reinitialiser/{token}', [AuthController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/mot-de-passe/reinitialiser', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('password.update');
});
Route::post('/deconnexion', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/panier', [CartController::class, 'show'])->name('cart.show');
    Route::post('/panier/{resource}', [CartController::class, 'store'])->name('cart.store');
    Route::delete('/panier/{resource}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/commandes', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/commandes/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/commandes/{order}/facture', [OrderController::class, 'invoice'])->name('orders.invoice');
    Route::get('/commandes/{order}/facture/renvoyer', [OrderController::class, 'openInvoiceFromResendLink'])->name('orders.invoice.resend.open');
    Route::post('/commandes/{order}/facture/renvoyer', [OrderController::class, 'resendInvoice'])->name('orders.invoice.resend');
    Route::post('/commandes/{order}/paiement', [PaymentController::class, 'initiateSelected'])->name('payments.initiate.selected');
    Route::post('/commandes/{order}/paiements/{provider}', [PaymentController::class, 'initiate'])->name('payments.initiate');
    Route::get('/bibliotheque', [LibraryController::class, 'index'])->name('library.index');
    Route::get('/lecture/{resource}', [LibraryController::class, 'read'])->name('resources.read');
    Route::get('/lecture/{resource}/pages/{page}', [LibraryController::class, 'page'])->whereNumber('page')->middleware('throttle:120,1')->name('resources.page');
    Route::get('/lecture/{resource}/fichier', [LibraryController::class, 'inline'])->middleware('throttle:60,1')->name('resources.inline');
    Route::get('/telechargements/{resource}', [LibraryController::class, 'download'])->middleware('throttle:12,1')->name('resources.download');
    Route::get('/livres/{book}/telechargement', [ApiBookController::class, 'downloadPurchased'])->middleware('throttle:12,1')->name('books.download');
});
Route::middleware(['auth', 'admin'])->prefix('administration')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/commandes', [AdminDashboardController::class, 'orders'])->name('orders.index');
    Route::get('/paiements', [AdminDashboardController::class, 'payments'])->name('payments.index');
    Route::get('/webhooks', [AdminDashboardController::class, 'webhooks'])->name('webhooks.index');
    Route::get('/utilisateurs', [AdminDashboardController::class, 'users'])->name('users.index');
    Route::get('/ressources', [ResourceManagementController::class, 'index'])->name('resources.index');
    Route::post('/ressources', [ResourceManagementController::class, 'store'])->middleware('throttle:12,1')->name('resources.store');
    Route::get('/ressources/{resource}/modifier', [ResourceManagementController::class, 'edit'])->name('resources.edit');
    Route::patch('/ressources/{resource}', [ResourceManagementController::class, 'update'])->middleware('throttle:12,1')->name('resources.update');
});

Route::prefix('api')->middleware('throttle:120,1')->group(function () {
    Route::get('/csrf-token', fn () => ['csrf_token' => csrf_token()]);
    Route::get('/auth/me', [AuthApiController::class, 'me']);
    Route::post('/auth/register', [AuthApiController::class, 'register'])->middleware('guest');
    Route::post('/auth/login', [AuthApiController::class, 'login'])->middleware('guest');
    Route::get('/metadata', [MetadataController::class, 'index']);
    Route::get('/resources', [ResourceCatalogController::class, 'index']);
    Route::get('/books', [ApiBookController::class, 'index']);
    Route::get('/books/{book:slug}', [ApiBookController::class, 'show']);
    Route::get('/reading-documents', [ReadingDocumentController::class, 'index']);
    Route::get('/reading-documents/{document}/preview', [ReadingDocumentController::class, 'preview'])->name('api.reading-documents.preview');

    Route::middleware('auth')->group(function () {
        Route::post('/auth/logout', [AuthApiController::class, 'logout']);
        Route::get('/dashboard', DashboardController::class);
        Route::get('/loans', [LoanController::class, 'index']);
        Route::post('/loans', [LoanController::class, 'store']);
        Route::post('/loans/{loan}/pay-fee', [LoanController::class, 'payFee']);
        Route::post('/loans/{loan}/return', [LoanController::class, 'markReturned']);
        Route::delete('/loans/{loan}', [LoanController::class, 'destroy']);
        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::post('/books/{book}/reservations', [ReservationController::class, 'store']);
        Route::post('/books/{book}/purchase', [ApiBookController::class, 'purchase']);
        Route::get('/books/{book}/download', [ApiBookController::class, 'downloadPurchased']);
        Route::delete('/reservations/{reservation}', [ReservationController::class, 'cancel']);
        Route::get('/reading-documents/{document}/reader', [ReadingDocumentController::class, 'reader']);
        Route::get('/reading-documents/{document}/pages/{page}', [ReadingDocumentController::class, 'page'])->whereNumber('page')->name('api.reading-documents.page');
        Route::get('/reading-documents/{document}/read', [ReadingDocumentController::class, 'read']);
        Route::post('/reading-documents/{document}/presence', [ReadingPresenceController::class, 'touch']);
        Route::delete('/reading-documents/{document}/presence', [ReadingPresenceController::class, 'leave']);

        Route::middleware('admin')->group(function () {
            Route::post('/books', [ApiBookController::class, 'store']);
            Route::put('/books/{book}', [ApiBookController::class, 'update']);
            Route::delete('/books/{book}', [ApiBookController::class, 'destroy']);
            Route::post('/authors', [MetadataController::class, 'storeAuthor']);
            Route::post('/book-categories', [MetadataController::class, 'storeCategory']);
            Route::post('/admin/loans', [LoanController::class, 'storeForMember']);
            Route::get('/members', [MemberController::class, 'index']);
            Route::get('/users', [UserManagementController::class, 'index']);
            Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole']);
            Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
            Route::patch('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus']);
            Route::post('/reading-documents', [ReadingDocumentController::class, 'store']);
            Route::get('/reading-presences', [ReadingPresenceController::class, 'index']);
        });
    });
});
