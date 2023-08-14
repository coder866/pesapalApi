<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;


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
    
    // $response = Http::get('https://type.fit/api/quotes/');
    // $responseData = $response->json();
    
    //      $quotes=Arr::random($responseData);
        
    //     return $quotes['text'] . ' ~' . $quotes['author'];
    return view('welcome');
});

Route::get('inspire', function(\Xnjuguna\pesapal\PaymentsApi $payt) {
    return $payt->inspire();
});


