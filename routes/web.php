<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use App\Http\Middleware\PaymentSessionTimeout;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


// Route::get('payment', [TransactionController::class, 'validPayment']);
Route::post('getAccount', [TransactionController::class, 'getAccount']);
// Route::post('deposit', [TransactionController::class, 'deposit'])->name('deposit');
Route::get('/sessionTimeOut', [TransactionController::class, 'sessionTimeOut'])->name('sessionTimeOut');
Route::post('/returnSession', [TransactionController::class, 'returnSession'])->name('returnSession');

Route::post('/updateTransaction', [TransactionController::class, 'updateClientTransaction'])->name('updateTransaction');
Route::post('/updateTxid', [TransactionController::class, 'updateTxid'])->name('updateTxid');

Route::get('/returnTransaction', [TransactionController::class, 'returnTransaction'])->name('returnTransaction');
Route::post('/returnUrl', [TransactionController::class, 'returnUrl'])->name('returnUrl');

Route::middleware([PaymentSessionTimeout::class])->group(function () {
    Route::get('payment', [TransactionController::class, 'payment']);
});

Route::get('/payment-session-expired', function () {
    return Inertia::render('Welcome');
})->name('expired');

require __DIR__.'/auth.php';
