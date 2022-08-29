<?php
/***
 *
 */

namespace App\Utils;

use GuzzleHttp\Client;

class OrangeMoney
{
    const BASE_URL = "https://api.orange.com/";

    private $token;
    private $transaction_status;

    private $client ;
    /**
     * @var string or null
     */
    private  $auth_header;
    /**
     * @var string or null
     */
    private  $credentials;
    /**
     * @var string or null
     */
    private  $merchant_key;

     /**
     * @var string or null
     */
    private  $return_url;
     /**
     * @var string or null
     */
    private  $cancel_url;
     /**
     * @var string or null
     */
    private  $notif_url;
    /**
     *
     */
    public function __construct() {
        // Credentials: <Base64 value of UTF-8 encoded “username:password”>
     
    
        $this->client = new Client([\GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath(),'base_uri' => self::BASE_URL]);
      
        $this->auth_header= config('app.om_auth_header');
        $this->merchant_key= config('app.om_merchant_key');
        $this->return_url= config('app.om_return_url');
        $this->cancel_url= config('app.om_cancel_url');
        $this->notif_url= config('app.om_notif_url');
        //
        $this->fetchToken();

    
    }

    public function fetchToken()
    {
  
        $token_url = "https://api.orange.com/oauth/v2/token";


        $token_headers = [
            'Authorization' => 'Basic '. config('app.om_auth_header'),
            'Accept'=>'application/json',
        ];
        $token_request = new Client([
            \GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath(),
            'headers' => $token_headers,
        ]);

        $token_response = $token_request->request("POST", $token_url, [
            'form_params' => [
                'grant_type' => 'client_credentials'
            ]
        ]);

        $token_response_data = json_decode((string)$token_response->getBody()->getContents(),true);
        $token_value = $token_response_data["access_token"];
        $this->token = $token_value;
        
        return $token_value;
    }

    public function getToken()
    {
        return $this->token;
    }
    public function getStatus()
    {
        return $this->transaction_status;
    }

    // **** Actually just requests the payment_url and pay_token for payment via orange webportal
    public function webPayment(array $data)
    {
        $base_url = "https://api.orange.com";
        $pay_url = "/orange-money-webpay/ci/v1/webpayment";

        $order_id = "ADJEMIN_ORDER_0".rand(10000,90000)."_00".rand(1000,9000);
        $http = new Client([\GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath(),'base_uri' => $base_url]);

        if("prod" == "dev"){
            $currency = "OUV";
        }else{
            $currency = "XOF";
        }
        // TODO Make this bitch a property var for 'sandbox'

        $response = $http->post($pay_url, [
            'json' => [
                'merchant_key'=> config('app.om_merchant_key'),
                // 'currency'=> $data['currency_code'],
                'currency'=> $currency,
                // 'order_id'=> $data['order_id'],
                'order_id'=> $order_id,
                'amount'=> $data['amount'],
                "return_url" => $data['return_url'],
                "cancel_url" => $data['cancel_url'],
                "notif_url" => $data['notif_url'],
                'lang'=> 'fr',
                'reference'=> 'Adjemin Pay'
            ],
            'headers' => [
                // 'Authorization' => 'Bearer '.$token_value,
                'Authorization' => 'Bearer '.$this->getToken(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);


        $responseData = json_decode($response->getBody()->getContents(), true);
        return [
            'paymentResponse' => $responseData,
            'order' => [
                'id' => $order_id,
                'amount' => $data['amount']
            ]
        ];
        // $orange_payment_base_uri =
        // $orange_payment_url = $data["payment_url"];
        // return response()->json(["data" => $data, "url" => $orange_payment_url]);
    }
    // **** Pays directly with otp code and phone number without orange webportal
    public function directPayment(array $data)
    {

        

        $base_url = "https://mpayment.orange-money.com";
        $payment_url ="/ci/mpayment/finalize";

        $http = new Client([\GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath(), 'base_uri' => $base_url]);
        $response = $http->post($payment_url, [
            'json' => [
                'Msisdn' => $data['telephone'],
                'Token' => $data['token'],
                'Otp' => $data['otp']
            ],
            'headers' => [
                // 'Authorization' => 'Bearer '.$token_value,
                'Authorization' => 'Bearer '.$this->getToken(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);


        $responseData = json_decode($response->getBody()->getContents(), true);
        return [
            'responseData' => $responseData,
        ];
        // $orange_payment_base_uri =
        // $orange_payment_url = $data["payment_url"];
        // return response()->json(["data" => $data, "url" => $orange_payment_url]);
    }



    // Request to get pay_token and notify_token
    public function getPayToken(array $data)
    {
        $base_url = "https://api.orange.com";
        $pay_url = "/orange-money-webpay/".config('app.om_env')."/v1/webpayment";

        $order_id = "ADJEMIN_ORDER_0".rand(10000,90000)."_00".rand(1000,9000);
        $http = new Client();

        if("prod" == "dev"){
            $currency = "OUV";
        }else{
            $currency = "XOF";
        }
        // TODO Make this bitch a property var for 'sandbox'

        $response = $http->post($pay_url, [
            'json' => [
                'merchant_key'=> config('app.om_merchant_key'),
                // 'currency'=> $data['currency_code'],
                'currency'=> $currency,
                // 'order_id'=> $data['order_id'],
                'order_id'=> $order_id,
                'amount'=> $data['amount'],
                "return_url" => $data['return_url'],
                "cancel_url" => $data['cancel_url'],
                "notif_url" => $data['notif_url'],
                'lang'=> 'fr',
                'reference'=> 'Adjemin Pay'
            ],
            'headers' => [
                // 'Authorization' => 'Bearer '.$token_value,
                'Authorization' => 'Bearer '.$this->getToken(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);


        $responseData = json_decode($response->getBody()->getContents(), true);
        return [
            'paymentResponse' => $responseData,
            'order' => [
                'id' => $order_id,
                'amount' => $data['amount']
            ]
        ];
        // $orange_payment_base_uri =
        // $orange_payment_url = $data["payment_url"];
        // return response()->json(["data" => $data, "url" => $orange_payment_url]);
    }



    public function checkTransactionStatus(array $data)
    {
        # code...
        $http = new Client([\GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath(), 'base_uri' => self::BASE_URL]);
        $check_transaction_url = "orange-money-webpay/ci/v1/transactionstatus";

        // $b = [
        //     "order_id" => $data["order_id"],
        //     "amount" => $data["amount"],
        //     "pay_token" => $data["pay_token"]
        // ];

        // $b = json_encode($b);

        /* var_dump($b); die();*/
        // $options = [
        //     'headers' => [
        //         'Authorization' => 'Bearer ' . $this->getToken(),
        //         'Accept' => 'application/json',
        //         'Content-Type' => 'application/json'
        //     ],
        //     'json' => $b
        // ];

        $response = $http->post($check_transaction_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'json' => [
                "order_id" => $data["order_id"],
                "amount" => $data["amount"],
                "pay_token" => $data["pay_token"]
            ]
        ]);

        $responseData = json_decode($response->getBody()->getContents(), true);
        $this->transaction_status = $responseData['status'];
        return $responseData;
    }
}