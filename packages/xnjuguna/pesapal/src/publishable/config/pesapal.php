<?php

return [
    'prefix' => '/api/pesapal',
    'middleware' => ['api', 'auth', 'sanctum'],
    'consumer_key' => env('PESAPAL_KEY', 'qkio1BGGYAXTu2JOfm7XSXNruoZsrqEW'),
    'consumer_secret' => env('PESAPAL_SECRET', 'osGQ364R49cXKeOYSpaOnT++rHs='),
    'ipn_id' => env('PESAPAL_IPN_ID', 'b47aaa49-1fd5-48c6-9b77-e02116131ab4'),
    'callback_url' => env('APP_URL') . '/api/ipn',

];
