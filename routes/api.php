<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('payments')->group(function () {
    Route::get('/stk-push', [PaymentController::class, 'initiateStkPush']);
    Route::get('/token', [PaymentController::class, 'token']);
    Route::post('/callback', [PaymentController::class, 'stkCallback'])->name('callback');
});

