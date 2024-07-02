<?php

use App\Http\Controllers\api\PaymentReturnController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/returnParams', [PaymentReturnController::class, 'returnUrl']);