<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
// use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function token()
    {
        $consumerKey = env('CONSUMER_KEY');
        $consumerSecret = env('CONSUMER_SECRET');
        $accessTokenUrl = env('SAFARICOM_AUTH_URL');

        $headers = ['content-Type:application/json; charset=utf8'];
        $curl = curl_init($accessTokenUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curl, CURLOPT_HEADER,0);
        curl_setopt($curl, CURLOPT_USERPWD, $consumerKey .":". $consumerSecret);
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $result = json_decode($result, true);

        // echos the accessToken extracted from the result
        $accessToken = $result['access_token'] ?? null;
        curl_close($curl);

        return $accessToken;
    }

    public function initiateStkPush()
    {
        $accessToken = $this->token();
        $url = env('SAFARICOM_STKPUSH_URL');
        $passKey = env('PASSKEY_STK');
        $BusinessShortCode = env('PAYBILL_NUMBER');
        $Timestamp = Carbon::now()->format('YmdHis');
        $password = base64_encode($BusinessShortCode . $passKey . $Timestamp);
        $TransactionType = env('TRANSACTION_TYPE_PAYBILL');
        $Amount = 1;
        $PartyA = env('PHONE_NUMBER');
        $PartyB = env('PAYBILL_NUMBER');
        $PhoneNumber = env('PHONE_NUMBER');
        $CallbackUrl = env('CALLBACK_ZYN');
        $AccountReference = env('COMPANY_NAME');
        $TransactionDesc = 'Payment for Goods and Services';


        $response = Http::withToken($accessToken)->post($url, [
            'BusinessShortCode' => $BusinessShortCode,
            'Password' => $password,
            'Timestamp' => $Timestamp,
            'TransactionType' => $TransactionType,
            'Amount' => $Amount,
            'PartyA' => $PartyA,
            'PartyB' => $PartyB,
            'PhoneNumber' => $PhoneNumber,
            'CallBackURL' => $CallbackUrl,
            'AccountReference' => $AccountReference,
            'TransactionDesc' => $TransactionDesc,

        ]);

        return $response;

    }

    public function stkCallback()
    {


    }
}
