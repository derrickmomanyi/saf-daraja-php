<?php

namespace App\Http\Controllers;

use App\Models\StkRequest;
use Carbon\Carbon;
// use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Throwable;
use Illuminate\Http\Request;


class PaymentController extends Controller
{
    public function token()
    {
        $consumerKey = env('CONSUMER_KEY');
        $consumerSecret = env('CONSUMER_SECRET');
        $accessTokenUrl = env('SAFARICOM_AUTH_URL');

        $headers = ['Content-Type: application/json; charset=utf8'];
        $curl = curl_init($accessTokenUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curl, CURLOPT_HEADER,0);
        curl_setopt($curl, CURLOPT_USERPWD, $consumerKey .":". $consumerSecret);
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $result = json_decode($result, true);

        $accessToken = $result['access_token'] ?? null;
        curl_close($curl);


        return $result['access_token'];
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
        $CallbackUrl = env('CALLBACK_URL');
        $AccountReference = env('COMPANY_NAME');
        $TransactionDesc = 'Payment for Goods and Services';

        try
        {
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
        }
        catch(Throwable $e)
        {
            return $e->getMessage();
        }

        $res=json_decode($response);

        $ResponseCode=$res->ResponseCode;
        if($ResponseCode==0){
            $MerchantRequestID=$res->MerchantRequestID;
            $CheckoutRequestID=$res->CheckoutRequestID;
            $CustomerMessage=$res->CustomerMessage;

            //save to database
            $payment= new StkRequest;
            $payment->phone=$PhoneNumber;
            $payment->amount=$Amount;
            $payment->reference=$AccountReference;
            $payment->description=$TransactionDesc;
            $payment->MerchantRequestID=$MerchantRequestID;
            $payment->CheckoutRequestID=$CheckoutRequestID;
            $payment->status='Requested';
            $payment->save();

            return $CustomerMessage;
        }

    }

    public function stkCallback()
    {
        $data=file_get_contents('php://input');
        Storage::disk('local')->put('stk.txt',$data);

        $response=json_decode($data);

        $ResultCode=$response->Body->stkCallback->ResultCode;

        if($ResultCode==0){
            $MerchantRequestID=$response->Body->stkCallback->MerchantRequestID;
            $CheckoutRequestID=$response->Body->stkCallback->CheckoutRequestID;
            $ResultDesc=$response->Body->stkCallback->ResultDesc;
            $Amount=$response->Body->stkCallback->CallbackMetadata->Item[0]->Value;
            $MpesaReceiptNumber=$response->Body->stkCallback->CallbackMetadata->Item[1]->Value;
            //$Balance=$response->Body->stkCallback->CallbackMetadata->Item[2]->Value;
            $TransactionDate=$response->Body->stkCallback->CallbackMetadata->Item[3]->Value;
            $PhoneNumber=$response->Body->stkCallback->CallbackMetadata->Item[3]->Value;

            $payment=Stkrequest::where('CheckoutRequestID',$CheckoutRequestID)->firstOrfail();
            $payment->status='Paid';
            $payment->TransactionDate=$TransactionDate;
            $payment->MpesaReceiptNumber=$MpesaReceiptNumber;
            $payment->ResultDesc=$ResultDesc;
            $payment->save();

        }else{

        $CheckoutRequestID=$response->Body->stkCallback->CheckoutRequestID;
        $ResultDesc=$response->Body->stkCallback->ResultDesc;
        $payment=Stkrequest::where('CheckoutRequestID',$CheckoutRequestID)->firstOrfail();

        $payment->ResultDesc=$ResultDesc;
        $payment->status='Failed';
        $payment->save();

        }

    }
}
