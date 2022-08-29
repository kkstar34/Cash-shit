<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->string('currency_code');
            $table->decimal('amount', 12, 2);
            $table->string('designation')->nullable();
            $table->string('type');
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->string('payment_method_code')->nullable();
            $table->boolean('is_initiated')->nullable()->default(0);
            $table->boolean('is_completed')->nullable()->default(0);
            $table->boolean('is_waiting')->nullable()->default(0);
            $table->boolean('is_canceled')->nullable()->default(0);
            $table->unsignedBigInteger('card_provider_id')->nullable(); // PayPal, VISA, UBA
            $table->boolean('is_approuved')->nullable();  // Transaction is approuved by the payeur
            $table->datetime('canceled_at')->nullable(); // Canceled date
            $table->datetime('approuved_at')->nullable(); // Approbation date
            $table->string('status')->nullable();
            $table->string('reference')->nullable();
            $table->text('client_reference')->nullable();
            $table->text('reason')->nullable();
            $table->string('notify_url')->nullable();
            $table->text('error_meta_data')->nullable();
            $table->text('provider_payment_id')->nullable(); // orange payment url, mtn external id
            $table->string('buyer_name')->nullable();
            $table->string('buyer_phone_number')->nullable();
            $table->string('return_url')->nullable();
            $table->string('cancel_url')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
