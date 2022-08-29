<?php

use App\Http\Controllers\API\NetworkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\PayApiController;
use App\Models\Transaction;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware(['client'])->group(function () {
Route::post('make_transaction',[PayApiController::class, "pay"]);
Route::get('get/status/{reference}', function($reference){

    $transaction = Transaction::where('reference', $reference)->first();
    return response()->json([
        'designation' => $transaction->designation,
        'status' => $transaction->status,
        'amount' => $transaction->amount,
        'reference'=>$transaction->reference,
        'buyer_name' => $transaction->buyer_name,
        'buyer_phone_number' => $transaction->buyer_phone_number,
        'payment_method_code' => $transaction->payment_method_code,
        'provider_payment_id' => $transaction->provider_payment_id,
        'notify_url' => $transaction->notify_url,
        'reason' => $transaction->reason
    ]);

});

});

Route::post('verify-network', [NetworkController::class,  'verify']);

