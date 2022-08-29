<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Utils\AxaZaraPaySDK;
use Exception;
use Illuminate\Bus\Queueable;
use App\Models\TransactionNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException ;

class CheckTransactionStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;
    public $tries = 2;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        logger($request['reference']);
        $this->data = $request;
        logger('entré 1');
        
      
    }




    

     

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $input = $this->data;
        logger($input['reference']);
        $input = $this->data;
        logger('entré 3');
    

        /* if($input == null){
            return response()->json([
                "message" => "Invalid data"
            ]);
        } */


        
            

          $transaction = Transaction::where('provider_payment_id', $input['reference'])->first();

           
           

            if($transaction == null){
                logger('tras');
                return response()->json([
                    "message" => "Transaction not found"
                ]);
            }

            $mtn = new AxaZaraPaySDK('MTN');

            if($transaction->reference == null){
                return response()->json([
                    "message" => "Transaction id invalid"
                ]);

            }
            $response_code = 00;

            $MAX_TRIES = 60; // TODO make this a config var
            // TODO make transaction status uncompleted if timeout
            $tries = 0;
           
            // try it around for a full 5.5 minutes if the status is still pending
            logger('je suis au moins arrivé ici');
            do{
                $tries++;
                logger('je suis dans le do');
                try {
                    $response = $mtn->followPaymentStatus($transaction->provider_payment_id);
                    logger('le try a marché');
                } catch(ClientException $e){
                    $transaction->error_meta_data = "Exception on follow status  $tries";
                    $transaction->reason = "La transaction a eu une erreur lors du checking de status";
                  
                    continue;
                }
                // SUCCESSFUL // FAILED // PENDING
                
                $transaction->status = $response['status'];

                // record reason if failed
                if($response['status'] == "SUCCESSFUL"){
                    // if SUCCESSFUL
                    
                    $response_code = 11;
                    $transaction->is_waiting = 0;
                    $transaction->is_approuved = 1;
                    $transaction->approuved_at = now();
                    $transaction->save();
                    //notify when transaction achived, backend merchant
                    if($transaction->notify_url){
                        $this->notifyUrl($transaction->notify_url, [
                            'transaction_reference' => $transaction->reference,
                            'status' => $transaction->status,
                            'data' => $transaction,
                        ]);
                    }

                    return 0;
                    break;
                }
                if($response['status'] == "FAILED"){
                    logger($response['reason']);
                    if($response['reason'] == "COULD_NOT_PERFORM_TRANSACTION"){
                        $response_code = -11;
                    // $transaction->reason = $response['reason'];
                    $transaction->status = "CANCELLED";
                    $transaction->reason = "Le paiement a été annulé";
                    $transaction->is_waiting = 0;
                    $transaction->is_canceled = 1;
                    $transaction->canceled_at = now();
                    $transaction->save();

                    //notify when transaction failed, backend merchant
                    if($transaction->notify_url){
                        try{
                        $this->notifyUrl($transaction->notify_url, [
                            'transaction_reference' => $transaction->reference,
                            'status' => $transaction->status,
                            'data' => $transaction,
                        ]);
                        }catch(\Exception $e){
                            //
                        }
                    }
                    return 0;
                    break;

                    }else{
                        $response_code = -11;
                        // $transaction->reason = $response['reason'];
            
                        // $transaction->reason = $response['reason'];
                        $transaction->status = "FAILED";
                        $transaction->reason = "La transaction a echouée, delai depassé";
                        $transaction->is_waiting = 0;
                        $transaction->is_canceled = 1;
                        $transaction->canceled_at = now();
                        $transaction->save();

                        //notify when transaction failed, backend merchant
                        if($transaction->notify_url){
                            try{
                            $this->notifyUrl($transaction->notify_url, [
                                'transaction_reference' => $transaction->reference,
                                'status' => $transaction->status,
                                'data' => $transaction,
                            ]);
                            }catch(\Exception $e){
                                //
                            }
                        }
                        return 0;
                        break;
                    }
                    
                }
                // try again if still pending
                if ($response['status'] == "PENDING") {
                    $tries++;
                    sleep(5);
                    continue;
                }
            } while ($tries < $MAX_TRIES);

            // Notifying notifyUrl

       
        if($transaction->notify_url){
            try{
                $this->notifyUrl($transaction->notify_url, [
                    'transaction_reference' => $transaction->reference,
                    'status' => $transaction->status,
                    'data' => $transaction,
                   
                ]);
            } catch(\Exception $e){
                //
            }
        }

        $response_code = -11;
        // $transaction->reason = $response['reason'];
        $transaction->status = "FAILED";
        $transaction->reason = "La transaction a echouée, delai depassé";
        $transaction->is_waiting = 0;
        $transaction->save();

         

        
    }


    



    public function notifyUrl($url, $data, $headers = []){


        
        $request = new Client([\GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath(),


        'connect_timeout' => 60.0, // Seconds.
        'timeout' => 1800.0, // Seconds.

        ]
        );

        $transaction = Transaction::where('reference', $data['transaction_reference'])->first();


        $body = [
            'status' => $transaction->status,
            'designation' => $transaction->designation,
            'transaction_id' => $transaction->reference,
            'client_reference' => $transaction->client_reference,
            'amount' => $transaction->amount,
            'phone_number' => $transaction->buyer_phone_number,
            'buyer_name' => $transaction->buyer_name,
            'currency_code' => $transaction->currency_code,
            'cancelled_at' => $transaction->canceled_at,
            'approuved_at' => $transaction->approuved_at,
            'transaction_type' => $transaction->payment_method_code,
        ];


       
        try {
           // $response = $request->post($transaction->notify_url, $option);
            $response = $request->post($url, [
                'form_params' => $body
            ]);
              
            $transaction = Transaction::where('reference', $transaction->reference)->first();
    
                
            $transaction_notification_exist = TransactionNotification::where('transaction_id', $transaction->id)->first();

            if(!empty($transaction_notification_exist)){
                $transaction_notification_exist->update(
                    [
                     
                        'transaction_id' => $transaction->id,
                        'notify_url' => $transaction->notify_url,
                        
                        'params' => json_encode($body),
                        'status_code' => $response->getStatusCode(),
                      
        
                        'response' =>   $response->getBody(),
                        'status' =>  $transaction->status,
                        
                        ]
                );


                

         
                return true;
            }


            $transaction_notification = TransactionNotification::create(
                [
                     
                'transaction_id' => $transaction->id,
                'notify_url' => $transaction->notify_url,
                
                'params' => json_encode($body),
                'status_code' => $response->getStatusCode(),
              

                'response' =>  $response->getBody(),
                'status' =>  $transaction->status,
                ]
                );




            

               

                    
                 
             

            
            
           // $response = (array)json_decode($response, true);
    
          
            return response()->json([
                
                "response" => $response,
                "status" => $response->getStatusCode()
    
            ]);
            } catch(ClientException  $e){
    
            
                $transaction = Transaction::where('reference', $transaction->reference)->first();
    
                
                $transaction_notification_exist = TransactionNotification::where('transaction_id', $transaction->id)->first();

                if(!empty($transaction_notification_exist)){
                 $transaction_notification_exist->update(
                        [
                         
                            'transaction_id' => $transaction->id,
                            'notify_url' => $transaction->notify_url,
                            
                            'params' => json_encode($body),
                            'status_code' => $e->getResponse()->getStatusCode() . " ". $e->getMessage(),
                          
            
                            'response' =>  $e->getResponse()->getReasonPhrase(),
                            'status' =>  $transaction->status,
                            ]
                    );

                    
                   
                
                    return true;
                }
    
                $response = $e->getResponse();
                $transaction_notification = TransactionNotification::create(
                    [
                         
                    'transaction_id' => $transaction->id,
                    'notify_url' => $transaction->notify_url,
                    
                    'params' => json_encode($body),
                    'status_code' => $e->getResponse()->getStatusCode() . " " . $e->getMessage(),
                  
    
                    'response' =>  $e->getResponse()->getReasonPhrase(),
                    'status' =>  $transaction->status,
                
                    ]
                    );
        

                    
                   
                   
            }
            catch(ConnectException $e){

                $transaction = Transaction::where('reference', $transaction->reference)->first();

    
                
                $transaction_notification_exist = TransactionNotification::where('transaction_id', $transaction->id)->first();

                if(!empty($transaction_notification_exist)){
                    $transaction_notification_exist->update(
                        [
                         
                            'transaction_id' => $transaction->id,
                            'notify_url' => $transaction->notify_url,
                            
                            'params' => json_encode($body) ,
                            'status_code' => '403',
                          
            
                            'response' =>  $e->getMessage(),
                            'status' =>  $transaction->status,
                         
                            ]
                    );


                    
                   
                    return true;

                }
               


                $transaction_notification = TransactionNotification::create(
                    [
                         
                    'transaction_id' => $transaction->id,
                    'notify_url' => $transaction->notify_url,
                    
                    'params' => json_encode($body),
                    'status_code' => '403',
                  
    
                    'response' =>  $e->getMessage(),
                    'status' =>  $transaction->status,
                 
                    ]
                    );


        
            }

            catch(Exception $e){

                $transaction = Transaction::where('reference', $transaction->reference)->first();
    
                
                $transaction_notification_exist = TransactionNotification::where('transaction_id', $transaction->id)->first();

                if(!empty($transaction_notification_exist)){
                 $transaction_notification_exist->update(
                        [
                         
                            'transaction_id' => $transaction->id,
                            'notify_url' => $transaction->notify_url,
                            
                            'params' => json_encode($body),

                            'status_code' => $e->getCode(),
                        
                        
            
                            'response' =>  $e->getMessage(),
                            'status' =>  $transaction->status,
                            'attemps' => intval($transaction->notification->attemps) + 1
                            ]
                    );

                  

                
                    return true;
                }
    
              
                $transaction_notification = TransactionNotification::create(
                    [
                         
                    'transaction_id' => $transaction->id,
                    'notify_url' => $transaction->notify_url,
                    
                    'params' => json_encode($body),

                    'status_code' => $e->getCode(),
                   
                  
    
                    'response' =>  $e->getMessage(),
                    'status' =>  $transaction->status,
                   
                    ]
                    );


                    


                   
        

            }



    }

}

