<?php

namespace App\Http\Controllers;

use App\Models\C2brequest;
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

    public function stkCallback(Request $request)
    {
        $response=json_decode($request->getContent());

         // Check if "ResultCode" is present in the response
         if (isset($response->Body->stkCallback->ResultCode)) {

          $ResultCode=$response->Body->stkCallback->ResultCode;

        if($ResultCode==0){
            $CheckoutRequestID=$response->Body->stkCallback->CheckoutRequestID;
            $ResultDesc=$response->Body->stkCallback->ResultDesc;
            $MpesaReceiptNumber=$response->Body->stkCallback->CallbackMetadata->Item[1]->Value;
            $TransactionDate=$response->Body->stkCallback->CallbackMetadata->Item[3]->Value;

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


    public function stkQuery()
    {
        $accessToken=$this->token();
        $BusinessShortCode =  env('PAYBILL_NUMBER');
        $passKey = env('PASSKEY_STK');
        $url = env('SAFARICOM_QUERY_URL');
        $Timestamp=Carbon::now()->format('YmdHis');
        $Password = base64_encode($BusinessShortCode . $passKey . $Timestamp);
        $CheckoutRequestID = env('CHECKOUTREQUEST_ID');

        $response=Http::withToken($accessToken)->post($url,[

            'BusinessShortCode'=>$BusinessShortCode,
            'Timestamp'=>$Timestamp,
            'Password'=>$Password,
            'CheckoutRequestID'=>$CheckoutRequestID
        ]);

        return $response;

    }


    public function registerUrl(){
        $accessToken=$this->token();
        $url = env('SAFARICOM_REGISTER_URL');
        $ShortCode = env('BUSINESS_SHORTCODE');
        $ResponseType='Completed';  //Cancelled
        $ConfirmationURL = env('CONFIRMATION_URL');
        $ValidationURL = env('VALIDATION_URL');

        $response=Http::withToken($accessToken)->post($url,[
            'ShortCode'=>$ShortCode,
            'ResponseType'=>$ResponseType,
            'ConfirmationURL'=>$ConfirmationURL,
            'ValidationURL'=>$ValidationURL
        ]);

        return $response;
    }

    public function Validation()
    {
        $data=file_get_contents('php://input');
        Storage::disk('local')->put('validation.txt',$data);

        //validation logic

        return response()->json([
            'ResultCode'=>0,
            'ResultDesc'=>'Accepted'
        ]);
    }

    public function Confirmation()
    {
        $data=file_get_contents('php://input');
        Storage::disk('local')->put('confirmation.txt',$data);
        //save data to DB
        $response=json_decode($data);
        $TransactionType=$response->TransactionType;
        $TransID=$response->TransID;
        $TransTime=$response->TransTime;
        $TransAmount=$response->TransAmount;
        $BusinessShortCode=$response->BusinessShortCode;
        $BillRefNumber=$response->BillRefNumber;
        $InvoiceNumber=$response->InvoiceNumber;
        $OrgAccountBalance=$response->OrgAccountBalance;
        $ThirdPartyTransID=$response->ThirdPartyTransID;
        $MSISDN=$response->MSISDN;
        $FirstName=$response->FirstName;
        $MiddleName=$response->MiddleName;
        $LastName=$response->LastName;

        $c2b=new C2brequest;
        $c2b->TransactionType=$TransactionType;
        $c2b->TransID=$TransID;
        $c2b->TransTime=$TransTime;
        $c2b->TransAmount=$TransAmount;
        $c2b->BusinessShortCode=$BusinessShortCode;
        $c2b->BillRefNumber=$BillRefNumber;
        $c2b->InvoiceNumber=$InvoiceNumber;
        $c2b->OrgAccountBalance=$OrgAccountBalance;
        $c2b->ThirdPartyTransID=$ThirdPartyTransID;
        $c2b->MSISDN=$MSISDN;
        $c2b->FirstName=$FirstName;
        $c2b->MiddleName=$MiddleName;
        $c2b->LastName=$LastName;
        $c2b->save();


        return response()->json([
            'ResultCode'=>0,
            'ResultDesc'=>'Accepted'
        ]);

    }
}
