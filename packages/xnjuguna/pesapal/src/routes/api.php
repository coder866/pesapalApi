<?php

use Xnjuguna\Pesapal\Controllers\PesapalPaymentsController;
use Illuminate\Support\Facades\Route;



// Authentication
Route::post('/auth/request-token', [PesapalPaymentsController::class, 'generateAuthToken']);

// IPN Registration
Route::post('/ipn/register', [PesapalPaymentsController::class, 'registerIPN']);

// Order Submission
Route::post('/transactions/submit-order', [PesapalPaymentsController::class, 'submitOrder']);

// Get Transaction Status
Route::get('/transactions/{orderTrackingId}/status', [PesapalPaymentsController::class, 'getPesapalTransactionStatus']);

// IPN Callback
Route::post('/ipn', [PesapalPaymentsController::class, 'handleIPNCallback']);

// List IPN Registrations
Route::get('/ipn/list', [PesapalPaymentsController::class, 'getIPNList']);

Route::get('/orders/list', [PesapalPaymentsController::class, 'getOrdersList']);