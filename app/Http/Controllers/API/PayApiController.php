<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\CheckTransactionStatusJob;
use App\Models\Application;
use App\Models\Merchant;
use App\Models\Request as ModelsRequest;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Utils\AxaZaraPaySDK;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Crypt;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use Goutte\Client as GoutteClient;
use App\Models\TransactionNotification;
use Illuminate\Contracts\Encryption\DecryptException;

class PayApiController extends Controller
{

    //for api 


    public function pay(Request $request)
    {





        $input = $request->all();





        $request->validate(
            [
                "amount" =>  ["required"],
                "designation" => ["required"],
                "operator" => ["required"],
                "currency_code" => ["required"],
                "client_id" => ["required"],
                "notify_url" => ["required"],
                "buyer_name" => ['required'],

            ]
        );

        $id = $request->operator == 'mtn' ? 2 : 3;
        ModelsRequest::create([
            'payment_method_id' => $id
        ]);








        // *** Creating a new transaction item in the database
        $transaction_reference = "AXA_PAY_ORDER_0" . rand(10000, 90000) . "_00" . rand(1000, 9000);
        $transaction = new Transaction();



        $transaction->amount = $input['amount'];
        $transaction->designation = $input['designation'];
        $transaction->reference = $transaction_reference;
        //$transaction->client_reference = $input['transaction_id'];
        $transaction->buyer_phone_number = $input['buyer_phone_number'];
        $transaction->notify_url = $input['notify_url'];
        $transaction->currency_code = $input['currency_code'];
        $transaction->buyer_name = $input['buyer_name'];


        // Transaction status
        $transaction->is_waiting = 1;



        switch ($input['operator']) {
            case 'om':

                $transaction->payment_method_id = 3;
                $transaction->status = "PENDING";
                /*   $transaction->user_id = $application->user->id;
                $transaction->merchant_id = $application->user->merchant->id;
                $transaction->application_id = $application->id; */
                $transaction->type = 0;
                $transaction->payment_method_code = "ORANGE-CI";


                $transaction->save();



                $sdkom = new AxaZaraPaySDK('Orange');


                $webpaymentResponse = $sdkom->webPayment([
                    'amount' => (float)$transaction->amount,
                    'telephone' => "225" . $transaction->phone_number,
                    'message' => $transaction->designation,
                    'note' => "note",
                    'reference' => $transaction->reference,
                ]);  //'client_id' => $application->client_id,



                if ($webpaymentResponse == null) {
                    $message = [
                        'statusCode' => 500,
                        "message" => "Aucune réponse de l'opérateur"
                    ];
                    return json_encode($message);
                }

                $paymentResponse = $webpaymentResponse['paymentResponse'];

                // Checks for transaction call to first url
                if ($paymentResponse['status'] == 201 || $paymentResponse['message'] == "OK") {
                    // $transaction->status = Status::where('slug', 'pending')->first()->name;

                    // $transaction->status_code = $paymentResponse['status'];
                    // $transaction->pay_token= $paymentResponse['pay_token'];
                    // $transaction->notif_token=$paymentResponse['notif_token'];
                    // $transaction->payment_url=$paymentResponse['payment_url'];
                    $transaction->provider_payment_id = $paymentResponse['payment_url'];

                    $transaction->is_waiting = 1;


                    $transaction->save();

                    
 
                    try {




                        $finalpaymentResponse = $sdkom->finalizePayment([
                            'telephone' => $input['buyer_phone_number'],
                            'token' => $paymentResponse['pay_token'],
                            'otp' => $input['otp']
                        ]);

                        // Waiting for orange to validate payment
                        if ($finalpaymentResponse == null) {
                            $message = [
                                'statusCode' => 500,
                                "message" => "Aucune réponse de l'opérateur"
                            ];
                            // update transaction to fail
                            $transaction->status = "FAILED";
                            $transaction->reason = "Aucune réponse de orange";
                            $transaction->is_initiated = 0;
                            $transaction->is_waiting = 0;
                            $transaction->save();

                            // notify here fail
                            if ($transaction->notif_url) {
                                try {
                                    $this->notifyUrl($transaction->notif_url, [
                                        'transaction_reference' => $transaction->reference,
                                        'status' => "FAILED",
                                        'data' => $transaction,

                                    ]);
                                } catch (\Exception $e) {
                                    //
                                }
                            }
                            return json_encode($message);
                        }

                        $responseData = $finalpaymentResponse['responseData'];

                        $transaction->status = $responseData['status'];

                        if ($responseData['status'] == "SUCCESS") {
                            // Successful
                            $transaction->status = "SUCCESSFUL";
                            $transaction->is_initiated = 0;
                            $transaction->is_waiting = 0;
                            $transaction->is_approuved = 1;
                            $transaction->approuved_at = now();
                            /*   $user = $transaction->user;
                            if ($user) {
                                $user->old_balance = $user->current_balance;
                                $user->current_balance += $transaction->amount;
                                $user->save();
                            } */
                            $transaction->save();
                            $responseCode = 11;

                            // Notifying notifyUrl
                            if ($transaction->notif_url) {
                                try {
                                    $this->notifyUrl($transaction->notif_url, [
                                        'transaction_reference' => $transaction->reference,
                                        'status' => $transaction->status,
                                        'data' => $transaction,
                                    ]);
                                } catch (\Exception $e) {
                                    //
                                }
                            }
                        }
                        if ($responseData['status'] == "FAILED") {
                            // $transaction->message = $responseData['message'];
                            $transaction->reason = $responseData['reason'];
                            $transaction->is_initiated = 0;
                            $transaction->is_waiting = 0;
                            $transaction->save();
                            $responseCode = -11;

                            // Notifying notifyUrl
                            if ($transaction->notif_url) {
                                try {
                                    $this->notifyUrl($transaction->notif_url, [
                                        'transaction_reference' => $transaction->reference,
                                        'status' => $transaction->status,
                                        'data' => $transaction,
                                    ]);
                                } catch (\Exception $e) {
                                    //
                                }
                            }
                        }



                        return response()->json([
                            'reference' => $transaction->reference,
                            'status' => $transaction->status,
                            'message' => $transaction->reason,
                            'transaction' => $transaction,
                        ]);
                    } catch (ClientException  $e) {
                        $reason = json_decode($e->getResponse()->getBody()->getContents());
                        $reason = $reason->description;

                        // $transaction->reason = $e->getResponse()->getReasonPhrase(). ' --'. $e->getMessage();
                        $transaction->reason = $reason;
                        $transaction->is_initiated = 0;
                        $transaction->is_waiting = 0;
                        $transaction->status = "FAILED";
                        $transaction->save();
                        $responseCode = -11;


                        // Notifying notifyUrl
                        if ($transaction->notif_url) {
                            try {
                                $this->notifyUrl($transaction->notif_url, [
                                    'transaction_reference' => $transaction->reference,
                                    'status' => $transaction->status,
                                    'data' => $transaction,
                                ]);
                            } catch (\Exception $e) {
                                //
                            }
                        }



                        return response()->json([
                            
                            'message' =>  $transaction->reason,
                            'status' => "FAILED",
                            'buyer_phone_number' => $transaction->buyer_phone_number,
                            'reference' => $transaction->reference,
                        ]);
                    }
                } else {
                    $transaction->status = "FAILED";
                    $transaction->reason = "Impossible de se connecter à Orange";

                    $transaction->save();

                    return response()->json([
                        'message' => $transaction->reason,
                        'data' => $transaction->buyer_phone_number
                    ], 500);
                }
                break;
            case 'mtn':

                $transaction->payment_method_id = 2;



                //code...
                $mtn = new AxaZaraPaySDK('MTN');

                $transaction->status = "PENDING";
                $transaction->is_waiting = 1;
                $transaction->payment_method_code = "MTN-CI";
                $transaction->type = 0;
                $transaction->save();

                $result = $mtn->webPayment([
                    'amount' => (float)$transaction->amount,
                    'telephone' => "225" . $transaction->buyer_phone_number,
                    'message' => $transaction->designation,
                    'note' => "note",
                    'reference' => $transaction->reference,
                ]);


                
                if (!is_array($result)) {
                    return $result->content();
                }




                $response = $mtn->getRequestResponse();

                if (!array_key_exists('status', $response)) {
                    return response()->json([
                        'message' => "Erreur Mtn",
                        'satus' => "ERROR",
                        'data' => $response,
                    ]);
                }

                $transaction->status = $response['status'];
                $transaction->save();

                if ($response['status'] == "FAILED") {
                    $transaction->reason = "Solde Insuffisant";
                    $transaction->is_waiting = 0;

                    $transaction->save();
                    return response()->json([
                        'message' => "Solde Insuffisant",
                        'status' => "FAILED",
                        'buyer_phone_number' => $transaction->buyer_phone_number,
                        'reference' => $transaction->reference,
                    ]);
                }
                if ($response['status'] == "PENDING") {

                    $data =  ['reference' => $response['externalId']];


                    CheckTransactionStatusJob::dispatch($data);
                    

                    return response()->json([
                        'reference' => $transaction->reference,
                        'message' => "Paiement en attente",
                        'status' => "PENDING",
                        'transaction' => $transaction,

                    ]);
                }

            case 'moov':
                return response()->json([
                    'message' => "Default swicth"
                ]);
                break;
            default:
                return response()->json([
                    'message' => "Default swicth"
                ]);
                break;
        }
    }



















    // ** Notification Url notification

    public function notifyUrl($url, $data, $headers = [])
    {



        $request = new Client(
            [
                \GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath(),


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
            'buyer_phone_number' => $transaction->buyer_phone_number,
            'buyer_name' => $transaction->buyer_name,
            'currency_code' => $transaction->currency_code,
            'cancelled_at' => $transaction->canceled_at,
            'approuved_at' => $transaction->approuved_at,
            'transaction_type' => $transaction->payment_method_code,
        ];



        try {
            // $response = $request->post($transaction->notif_url, $option);
            $response = $request->post($url, [
                'form_params' => $body
            ]);

            $transaction = Transaction::where('reference', $transaction->reference)->first();


            $transaction_notification_exist = TransactionNotification::where('transaction_id', $transaction->id)->first();

            if (!empty($transaction_notification_exist)) {
                $transaction_notification_exist->update(
                    [

                        'transaction_id' => $transaction->id,
                        'notify_url' => $transaction->notif_url,

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
                    'notify_url' => $transaction->notif_url,

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
        } catch (ClientException  $e) {


            $transaction = Transaction::where('reference', $transaction->reference)->first();


            $transaction_notification_exist = TransactionNotification::where('transaction_id', $transaction->id)->first();

            if (!empty($transaction_notification_exist)) {
                $transaction_notification_exist->update(
                    [

                        'transaction_id' => $transaction->id,
                        'notify_url' => $transaction->notif_url,

                        'params' => json_encode($body),
                        'status_code' => $e->getResponse()->getStatusCode() . " " . $e->getMessage(),


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
                    'notify_url' => $transaction->notif_url,

                    'params' => json_encode($body),
                    'status_code' => $e->getResponse()->getStatusCode() . " " . $e->getMessage(),


                    'response' =>  $e->getResponse()->getReasonPhrase(),
                    'status' =>  $transaction->status,

                ]
            );
        } catch (ConnectException $e) {

            $transaction = Transaction::where('reference', $transaction->reference)->first();



            $transaction_notification_exist = TransactionNotification::where('transaction_id', $transaction->id)->first();

            if (!empty($transaction_notification_exist)) {
                $transaction_notification_exist->update(
                    [

                        'transaction_id' => $transaction->id,
                        'notify_url' => $transaction->notif_url,

                        'params' => json_encode($body),
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
                    'notify_url' => $transaction->notif_url,

                    'params' => json_encode($body),
                    'status_code' => '403',


                    'response' =>  $e->getMessage(),
                    'status' =>  $transaction->status,

                ]
            );
        } catch (Exception $e) {

            $transaction = Transaction::where('reference', $transaction->reference)->first();


            $transaction_notification_exist = TransactionNotification::where('transaction_id', $transaction->id)->first();

            if (!empty($transaction_notification_exist)) {
                $transaction_notification_exist->update(
                    [

                        'transaction_id' => $transaction->id,
                        'notify_url' => $transaction->notif_url,

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
                    'notify_url' => $transaction->notif_url,

                    'params' => json_encode($body),

                    'status_code' => $e->getCode(),
                    'response' =>  $e->getMessage(),
                    'status' =>  $transaction->status,

                ]
            );
        }
    }
}
