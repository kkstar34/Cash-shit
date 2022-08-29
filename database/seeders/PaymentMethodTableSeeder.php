<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        PaymentMethod::create(
            [
                'title' => 'Moov',
                'logo' => 'moov_money.jpg',
               'reference' => 'Moov_CI',
                'country_code' => 'XOF',
                'is_active' => 0,
                'fees' => 100,
                'country_id' => 384,
            ]
            );


            PaymentMethod::create(
                [
                    'title' => 'MTN',
                    'logo' => 'mtn_money.jpg',
                   'reference' => 'MTN_CI',
                    'country_code' => 'XOF',
                    'is_active' => 1,
                    'fees' => 200,
                    'country_id' => 384,
                ]
                );

                PaymentMethod::create(
                    [
                        'title' => 'Orange',
                        'logo' => 'orange_money.jpg',
                       'reference' => 'ORANGE_CI',
                        'country_code' => 'XOF',
                        'is_active' => 1,
                        'fees' => 150,
                        'country_id' => 384,
                    ]
                    );

                    PaymentMethod::create(
                        [
                            'title' => 'Chèque',
                            'logo' => 'bank-cheque.png',
                           'reference' => 'Virement Bancaire',
                            'country_code' => 'XOF',
                            'is_active' => 0,
                            'fees' => 100,
                            'country_id' => 384,
                        ]
                        );

            
    }
}
