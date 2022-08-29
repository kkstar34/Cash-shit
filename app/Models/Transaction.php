<?php

namespace App\Models;

use Eloquent as Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Transaction
 * @package App\Models
 * @version December 12, 2019, 2:54 pm UTC
 *
 * @property integer sender_id
 * @property integer receiver_id
 * @property string amount
 * @property string reference
 * @property string designation
 * @property text adp_token
 * @property text adp_url
 * @property string transaction_fees
 * @property string transaction_fees_amount
 * @property string transaction_type
//  * @property integer status_id
 * @property string status
 * @property string currency_code
 * @property boolean is_pending
 * @property boolean is_blocked
 * @property boolean is_approuved
 * @property boolean is_canceled
 * @property boolean is_paid
 * @property boolean is_successfull
 * @property string|\Carbon\Carbon canceled_at
 * @property string|\Carbon\Carbon approuved_at
 * @property string|\Carbon\Carbon paid_at
 * @property string error_meta_data
 * @property integer psp_id
 * @property integer device_id
 * @property integer application_id
 */
class Transaction extends Model
{
    use SoftDeletes;

    public $table = 'transactions';


    protected $dates = ['deleted_at', 'approuved_at', 'canceled_at', 'paid_at'];


    public $fillable = [
        'sender_id',
        'receiver_id',
        'amount',
        'reference',
        'designation',
        'adp_token',
        'adp_url',
        'return_url',
        'cancel_url',
        'transaction_fees',
        'transaction_fees_amount',
        'transaction_type',
        // 'status_id',
        'status',
        'currency_code',
        'is_pending',
        'is_blocked',
        'is_approuved',
        'is_canceled',
        'is_paid',
        'is_successfull',
        'canceled_at',
        'approuved_at',
        'paid_at',
        'error_meta_data',
        'psp_id',
        'device_id',
        'application_id'
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'sender_id' => 'integer',
        'receiver_id' => 'integer',
        'amount' => 'integer',
        'reference' => 'string',
        'designation' => 'string',
        'adp_token' => 'text',
        'adp_url' => 'text',
        'transaction_fees' => 'string',
        'transaction_fees_amount' => 'string',
        'transaction_type' => 'string',
        // 'status_id' => 'integer',
        'status' => 'string',
        'currency_code' => 'string',
        'is_pending' => 'boolean',
        'is_blocked' => 'boolean',
        'is_approuved' => 'boolean',
        'is_canceled' => 'boolean',
        'is_paid' => 'boolean',
        'is_successfull' => 'boolean',
        'canceled_at' => 'datetime',
        'approuved_at' => 'datetime',
        'paid_at' => 'datetime',
        'error_meta_data' => 'string',
        'psp_id' => 'integer',
        'device_id' => 'integer',
        'application_id' => 'integer'
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [

    ];

    
}
