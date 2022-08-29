<?php

namespace App\Utils;

use App\Models\Transaction;
use GuzzleHttp\Client;

class MTNMoneyDisbursement {
    //private attribute variable;
    private $referenceId;
    private $token;
    private $balance;
    public $active;

    public $requestResponse;

    //process for get user Api
    //can throw exception
    public function __construct(){

         /* $this->setReferenceUUID4();

         $this->getTokenApi(); */

        
    }

    // set a UUID v4
    private function setReferenceUUID4(){
        
        $v = Transaction::latest()->first()->reference;
        $u = $v;
        // $hash = md5(uniqid());
        $hash = md5($u);

        $this->referenceId = sprintf('%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x3000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        );
    }


    public function generate_uuid() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

            // 16 bits for "time_mid"
            mt_rand( 0, 0xffff ),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand( 0, 0x0fff ) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand( 0, 0x3fff ) | 0x8000,

            // 48 bits for "node"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
    //
    public function generateTransactionReference(){
        

        $transactionId = $this->generate_uuid();
        

        return $transactionId;
    }


    // //Getters
    public function getReferenceUUID4(){
        // return $this->referenceId ?? env('REFERENCE_ID_MTN');
        return $this->referenceId ?? config('app.reference_id_mtn');
    }

    public function getMtnReference(){
        return config('app.reference_id_mtn');
    }

    public function getApiKey(){
        return config('app.user_api_mtn');
    }

    public function getToken(){
        return $this->token['access_token'];
    }

    public function getTarget(){
        return config('app.target_mtn') ;
    }

    public function getBalance(){
        return $this->balance['availableBalance'];
    }

    public function getCurrency(){
        return $this->balance['currency'];
    }


    //get a token from api
    private function getTokenApi(){

        
        // $credentials = \base64_encode($this->getMtnReference().":".$this->getApiKey());
        $option = [
            'headers' => [
                'Ocp-Apim-Subscription-Key' =>   config('app.subscription_key_mtn'),
                // 'Authorization' => 'Basic '. $credentials,
            ],
            'auth'  =>  [
                // $this->getReferenceUUID4(), $this->getApiKey()
                $this->getMtnReference(), $this->getApiKey()
            ]

        ];

        $request = new Client([\GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath()]
    );

    

    
        $url = "https://proxy.momoapi.mtn.com/collection/token/";
        $response = $request->post($url, $option);
       

     
        if($response->getStatusCode() == 200){
            $body = $response->getBody()->getContents();
            // return $this->token = (array) json_decode($body, true);
            return  (array) json_decode($body, true);
        }else{
            throw new \Exception("Erreur token", 500);
        }
    }

    //
    public function getAccessToken(){
        $tokenResult = $this->getTokenApi();

        
        if(is_array($tokenResult) && array_key_exists('access_token', $tokenResult)){
            return $tokenResult['access_token'];
        } else{

            return false;
        }
    }

    private function BuildPayment(float $amount, string $currency="", string $tel="", string $message="", string $notePaye="", string $externalId=""){
        return [
            "amount" => "$amount",
            "currency"  =>  "$currency",
            "externalId"    =>  "$externalId",
            // "externalId"    =>  $this->getReferenceUUID4(),
            "payer" =>  [
                "partyIdType" => "MSISDN",
                "partyId" => "$tel",
            ],
            "payerMessage"  =>  "$message",
            "payeeNote" =>  "$notePaye"
        ];
    }

    //request to pay
    public function requestToPay(float $amount, string $tel="", string $externalId="",string $message="", string $notePaye="", string $currency = "XOF"){
        if(empty($this->getAccessToken())){
            throw new \Exception("Unauthorized request ".$this->getAccessToken());
        }
        // $this->setReferenceUUID4();

        // $body = $this->BuildPayment($amount, $currency, $tel, $message, $notePaye, $externalId);
        // $body = $this->BuildPayment($amount, $externalId, $currency, $tel, $message, $notePaye, $externalId);
        $body = $this->BuildPayment($amount, $currency, $tel, $message, $notePaye, $externalId);


        $option = [
            'headers' => [
                'Ocp-Apim-Subscription-Key' =>   config('app.subscription_key_mtn'),
                // 'X-Reference-Id'    => $this->getReferenceUUID4(),
                'X-Reference-Id'    => "$externalId",
                'X-Target-Environment'  =>  $this->getTarget(),
                // 'Authorization'  =>  'Bearer '.$this->getToken()
                'Authorization'  =>  'Bearer '.$this->getAccessToken()
            ],
            'json' => $body
        ];

        // dd($option);

        $request = new Client([\GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath()]
    );
        $response = $request->post("https://proxy.momoapi.mtn.com/collection/v1_0/requesttopay", $option);
        // dd($response);
        if($response->getStatusCode() == 202 || $response->getStatusCode() == 200 ){
            // dd($response->getBody()->getContents());
            return $this->getPaymentResponse($externalId);
        }else{
            throw new \Exception("Payment Erreur", 500);
        }
    }


    //get payment response status
    // public function getPaymentResponse(){
    //     $option = [
    //         'headers' => [
    //             'Ocp-Apim-Subscription-Key' =>   env('SUBSCRIPTION_KEY_MTN'),
    //             'X-Target-Environment'  =>  $this->getTarget(),
    //             'Authorization'  =>  'Bearer '.$this->getToken()
    //         ]
    //     ];


    //     $request = new Client();

    //     $url = "https://proxy.momoapi.mtn.com/collection/v1_0/requesttopay/". $this->getReferenceUUID4();

    //     // dd($option);
    //     $response = $request->get($url, $option);
    //     // dd(json_decode($response->getBody()->getContents()));
    //     if($response->getStatusCode() == 200){
    //         $body = $response->getBody()->getContents();
    //         $json = (array) json_decode($body, true);
    //         $this->requestResponse = $json;
    //         return $this->requestResponse;
    //     }else{
    //         throw new \Exception("Payment response erreur", 500);
    //     }
    // }
    //get payment response status
    public function getPaymentResponse($transactionId){

        if(empty($this->getAccessToken())){
            throw new \Exception("Unauthorized request ".$this->getAccessToken());
        }
        $option = [
            'headers' => [
                'Ocp-Apim-Subscription-Key' =>   config('app.subscription_key_mtn'),
                'X-Target-Environment'  =>  $this->getTarget(),
                // 'Authorization'  =>  'Bearer '.$this->getToken()
                'Authorization'  =>  'Bearer '.$this->getAccessToken()
            ]
        ];
        $request = new Client([\GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath()]
    );

        // $url = "https://proxy.momoapi.mtn.com/collection/v1_0/requesttopay/".$this->getReferenceUUID4();
        $url = "https://proxy.momoapi.mtn.com/collection/v1_0/requesttopay/".$transactionId;
        // dd($option);
        $response = $request->get($url, $option);
        // dd(json_decode($response->getBody()->getContents()));
        if($response->getStatusCode() == 200){
            $body = $response->getBody()->getContents();
            $json = (array) json_decode($body, true);
            $this->requestResponse = $json;
            return $this->requestResponse;
        }else{
            throw new \Exception("Payment response erreur", 500);
        }
    }

    public function followTransactionStatus(string $mtnTransactionID){
        if(empty($this->getAccessToken())){
            throw new \Exception("Unauthorized request ".$this->getAccessToken());
        }
        $option = [
            'headers' => [
                'Ocp-Apim-Subscription-Key' =>   config('app.subscription_key_mtn'),
                'X-Target-Environment'  =>  $this->getTarget(),
                // 'Authorization'  =>  'Bearer '.$this->getToken()
                'Authorization'  =>  'Bearer '.$this->getAccessToken()
            ]
        ];
        $request = new Client([\GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath()]
    );

        $url = "https://proxy.momoapi.mtn.com/collection/v1_0/requesttopay/".$mtnTransactionID;

        // dd($option);
        $response = $request->get($url, $option);
        // dd(json_decode($response->getBody()->getContents()));
        if($response->getStatusCode() == 200){
            $body = $response->getBody()->getContents();
            $json = (array) json_decode($body, true);
            $this->requestResponse = $json;
            return $this->requestResponse;
        }else{
            throw new \Exception("Payment response erreur", 500);
        }
    }

    //get account balance api
    public function getAccountBalance(){
        $option = [
            'headers' => [
                'Ocp-Apim-Subscription-Key' =>   config('app.subscription_key_mtn'),
                'X-Target-Environment'  =>  $this->getTarget(),
                'Authorization'  =>  'Bearer '.$this->getToken()
            ]
        ];


        $request = new Client([\GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath()]
    );
        $response = $request->get("https://proxy.momoapi.mtn.com/collection/v1_0/account/balance", $option);

        if($response->getStatusCode() == 200){
            $body = $response->getBody()->getContents();
            $this->balance = (array) json_decode($body, true);
        }else{
            throw new \Exception("Erreur balance", 500);
        }

    }

    //return true if user exist
    public function getAccountHolder(string $accountHolderId, string $accountHolderIdType = "msisdn"){

      
      
        // if(empty($this->getToken()) || is_null($this->getToken())){
        //     $this->getTokenApi();
        // }
        if(empty($this->getAccessToken())){
            throw new \Exception("Unauthorized request ".$this->getAccessToken());
        }

        

        $option = [
            'headers' => [
                'Ocp-Apim-Subscription-Key' =>  config('app.subscription_key_mtn'),
                'X-Target-Environment'  =>  $this->getTarget(),
                // 'Authorization'  =>  'Bearer '.$this->getToken()
                'Authorization'  =>  'Bearer '.$this->getAccessToken()
            ]
        ];

        $request = new Client(
            [\GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath()]
        
        );


       
     

  
        $response = $request->get("https://proxy.momoapi.mtn.com/collection/v1_0/accountholder/$accountHolderIdType/$accountHolderId/active", $option);

        if($response->getStatusCode() == 200){
            $body = $response->getBody()->getContents();
            $result = (array) json_decode($body);
            $this->active = $result["result"];
            return $this->active;
        }else{
            throw new \Exception("Erreur Holder", 500);
        }
    }
}

?>
