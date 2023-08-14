<?php

use App\Http\Controllers\PesapalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

// Authentication
Route::post('/auth/request-token', [PesapalController::class, 'generateAuthToken']);

// IPN Registration
Route::post('/ipn/register', [PesapalController::class, 'registerIPN']);

// Order Submission
Route::post('/transactions/submit-order', [PesapalController::class, 'submitOrder']);

// Get Transaction Status
Route::get('/transactions/{orderTrackingId}/status', [PesapalController::class, 'getPesapalTransactionStatus']);

// IPN Callback
Route::post('/ipn', [PesapalController::class, 'handleIPNCallback']);

// List IPN Registrations
Route::get('/ipn/list', [PesapalController::class, 'getIPNList']);
