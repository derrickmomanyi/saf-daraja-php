<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::prefix('payments')->group(function () {
    Route::get('/stk-push', [PaymentController::class, 'initiateStkPush']);
    Route::get('/stk-query', [PaymentController::class, 'stkQuery']);
    Route::get('/registerUrl', [PaymentController::class, 'registerUrl']);
    Route::get('/validation', [PaymentController::class, 'Validation']);
    Route::get('/confirmation', [PaymentController::class, 'Confirmation']);
    Route::get('/token', [PaymentController::class, 'token']);
    Route::post('/callback', [PaymentController::class, 'stkCallback'])->name('callback');
});

