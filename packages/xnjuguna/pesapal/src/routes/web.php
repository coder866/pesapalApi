<?php

use Xnjuguna\Pesapal\Controllers\PesapalPaymentsController;
use Illuminate\Support\Facades\Route;

Route::get('pesa', PesapalPaymentsController::class);