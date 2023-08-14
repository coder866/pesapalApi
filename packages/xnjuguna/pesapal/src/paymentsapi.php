<?php

namespace Xnjuguna\pesapal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class PaymentsApi {
    public function inspire() {
        $response = Http::get('https://type.fit/api/quotes/');
        
        $jsonArr=$response->json();
        
        $quotes=Arr::random($jsonArr);
            
        return $quotes['text'] . ' ~' . $quotes['author'];
    }
}