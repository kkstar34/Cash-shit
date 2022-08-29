<?php

namespace App\Utils;

use Exception;
use App\Utils\MTNMoneyV2;
use App\Utils\OrangeMoney;
use App\Models\Application;
use App\Models\Status;
use App\Models\Transaction;

class AxaZaraPaySDK {
    private $initValue;
    private $platform;

    public function __construct(string $platform){
        $this->platform = $platform;
        $this->init();
        
    }

    private function init(){
        switch ($this->platform) {
            case 'MTN':
                try {
                    
                    $this->initValue = new MTNMoneyV2();
                    
                    
    
                } catch (\Exception $except) {
                    $message = [
                        'statusCode' => 500,
                        "message" => "Internal Error"
                    ];
                    return json_encode($message);
                    break;
                }
                break;
            case 'MTN-Disbursment':
                try {
                    
                    $this->initValue = new MTNMoneyDisbursement();
                    
                    
    
                } catch (\Exception $except) {
                    $message = [
                        'statusCode' => 500,
                        "message" => "Internal Error"
                    ];
                    return json_encode($message);
                    break;
                }
                break;
            case 'Orange':
                try {

                    $this->initValue = new OrangeMoney();
                } catch (\Exception $except) {
                    $message = [
                        'statusCode' => 500,
                        "message" => "Internal Error, Initialisation failled"
                    ];
                    return json_encode($message);
                    break;
                }
                break;
            default:
                $message = [
                    'statusCode' => 404,
                    "message" => "Undefined platform selected"
                ];
                echo json_encode($message);
                break;
        }
    }

    public function webPayment(array $request){

       
        switch ($this->platform) {
            case 'MTN':
                if(is_null($request['amount'])){

                    throw new Exception("Bad request, missing amount field", 400);
                }

                if(is_null($request['telephone'])){

                    throw new Exception("Bad request, missing telephone field", 400);
                }

                if(is_null($request['message'])){
                    throw new Exception("Bad request, missing message field", 400);
                }
               

                try {
                 
              
                    $this->initValue->getAccountHolder($request['telephone']);


                    if($this->initValue->active != true){
                    

                        throw new Exception("Bad request, missing note field", 400);
                    }
                } catch (\Exception $except) {
                   
                    throw new Exception("Erreur d'authentification du numéro", 1);
                }

                
                /*$application = Application::where('client_id', $request['client_id'])->first();
                if(is_null($application)){

                    throw new Exception("Invalid application", 404);
                }
*/
               
               $transaction = Transaction::where('reference',$request['reference'])->get()->first();

             

                if(is_null($transaction)){


                    throw new Exception("Invalid transaction", 404);
                }

                try {
                    $transactionReference = $this->initValue->generateTransactionReference();

                    $this->initValue->requestToPay((float) $request['amount'], $request['telephone'], $transactionReference, $request['message'],$request['note']);

                    // $transaction->mtnTransactionID = $this->initValue->getReferenceUUID4();
                    //$transaction->mtnTransactionID = $transactionReference;
                    $transaction->provider_payment_id = $transactionReference;

                   // $transaction->status = Status::where('slug', 'pending')->first()->name;

                    $transaction->save();

                    $response = $this->initValue->requestResponse;
                    return $response;
                    break;
                } catch (\Exception $except) {

                    throw new Exception($except->getMessage(), 404);
                }

                //end collection process

            case 'MTN-Disbursment': 

                if(is_null($request['amount'])){

                    throw new Exception("Bad request, missing amount field", 400);
                }

                if(is_null($request['telephone'])){

                    throw new Exception("Bad request, missing telephone field", 400);
                }

                if(is_null($request['message'])){
                    throw new Exception("Bad request, missing message field", 400);
                }
               

                try {
                 
              
                    $this->initValue->getAccountHolder($request['telephone']);


                    if($this->initValue->active != true){
                    

                        throw new Exception("Bad request, missing note field", 400);
                    }
                } catch (\Exception $except) {
                   
                    throw new Exception("Erreur d'authentification du numéro", 1);
                }

                
                /*$application = Application::where('client_id', $request['client_id'])->first();
                if(is_null($application)){

                    throw new Exception("Invalid application", 404);
                }
*/
               
               $transaction = Transaction::where('reference',$request['reference'])->get()->first();

             

                if(is_null($transaction)){


                    throw new Exception("Invalid transaction", 404);
                }

                try {
                    $transactionReference = $this->initValue->generateTransactionReference();

                    $this->initValue->requestToPay((float) $request['amount'], $request['telephone'], $transactionReference, $request['message'],$request['note']);

                    // $transaction->mtnTransactionID = $this->initValue->getReferenceUUID4();
                    //$transaction->mtnTransactionID = $transactionReference;
                    $transaction->provider_payment_id = $transactionReference;

                   // $transaction->status = Status::where('slug', 'pending')->first()->name;

                    $transaction->save();

                    $response = $this->initValue->requestResponse;
                    return $response;
                    break;
                } catch (\Exception $except) {

                    throw new Exception($except->getMessage(), 404);
                }


                // end disbursement process

            case 'Orange':
                if($request == null){
                    $message = [
                        'statusCode' => 400,
                        "message" => "Bad request, missing fields"
                    ];
                    echo json_encode($message);
                    break;

                }
                if(is_null($request['amount'])){
                    $message = [
                        'statusCode' => 400,
                        "message" => "Bad request, missing amount field"
                    ];
                    echo json_encode($message);
                    break;
                }
             /*  if(is_null($request['application_id'])){
                    $message = [
                        'statusCode' => 400,
                        "message" => "Bad request, missing client_id field"
                    ];
                    echo json_encode($message);
                    break;
                }
*/


               /* $application = Application::find($request['application_id']);;
                

                    if(is_null($application)){
                        $message = [
                            'statusCode' => 404,
                            "message" => "Invalid application"
                        ];
                        return json_encode($message);
                        break;
             }*/
                
                    $transaction = Transaction::where('reference',$request['reference'])->get()->first();

                    if(is_null($transaction)){
                        $message = [
                            'statusCode' => 404,
                            "message" => "Invalid transaction"
                        ];
                        return json_encode($message);
                        break;
                    }

                   

                    $payment = $this->initValue->webPayment([
                        'amount' => $request['amount'],

                        'return_url' => "https://adjeminpay.adjeminpay.net/payment/success",

                        'cancel_url' => "https://adjeminpay.adjeminpay.net/payment/success",

                        'notif_url' => "https://adjeminpay.adjeminpay.net/payment/success"

                    ]);

                    return $payment;

                break;

            default:
                $message = [
                    'statusCode' => 404,
                    "message" => "Invalid payment method"
                ];
                return json_encode($message);
            break;
        }
    }

    public function finalizePayment(array $request)
    {
        switch ($this->platform) {
            case 'Orange':
                return $this->initValue->directPayment($request);
            break;
            default:
                return "Bad platform";
            break;
        }
    }

    public function getRequestResponse()
    {
        switch ($this->platform) {
            case 'MTN':
                return $this->initValue->requestResponse;
            break;
            case 'Orange':
                return $this->initValue->getStatus();
            default:
                return "Bad platform";
            break;
        }
    }


    public function followPaymentStatus($mtnTransactionID)
    {
        switch ($this->platform) {
            case 'MTN':
                return $this->initValue->followTransactionStatus($mtnTransactionID);
            break;


            default:
                return "Bad platform";
            break;
        }
    }
    //
    public function checkTransactionStatus($data)
    {
        switch ($this->platform) {
            case 'Orange':
                return $this->initValue->checkTransactionStatus($data);
            break;
            default:
                return "Bad platform";
            break;
        }

    }





}
