<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function token()
    {
        $consumerKey = env('CONSUMER_KEY');
        $consumerSecret = env('CONSUMER_SECRET');
        $url = env('SAFARICOM_URL');

        $response = Http::withBasicAuth($consumerKey, $consumerSecret)->get($url);
        return $response;
    }
}
