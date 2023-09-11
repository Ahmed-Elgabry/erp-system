<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class TamaraPaymentController extends Controller
{
    public $tamara_api_code;
    public $is_enabled;
    protected $invoiceData;

    public function paymentConfig()
    {

        if(\Auth::check())
        {
            $payment_setting = Utility::getAdminPaymentSetting();
        }
        else
        {
            $payment_setting = Utility::getCompanyPaymentSetting($this->invoiceData->created_by);

        }


        $this->tamara_api_code = isset($payment_setting['tamara_api_code']) ? $payment_setting['tamara_api_code'] : '';
        $this->is_enabled = isset($payment_setting['is_tamara_enabled']) ? $payment_setting['is_tamara_enabled'] : 'off';
        return $this;
    }


    public function planPayWithTamara(Request $request)
    {

        $planID    = \Illuminate\Support\Facades\Crypt::decrypt($request->plan_id);
        $plan      = Plan::find($planID);
        $authuser  = \Auth::user();
        $coupon_id = '';
        if($plan)
        {

            $price = $plan->price;
            if(isset($request->coupon) && !empty($request->coupon))
            {
                $request->coupon = trim($request->coupon);
                $coupons         = Coupon::where('code', strtoupper($request->coupon))->where('is_active', '1')->first();
                if(!empty($coupons))
                {
                    $usedCoupun             = $coupons->used_coupon();
                    $discount_value         = ($price / 100) * $coupons->discount;
                    $plan->discounted_price = $price - $discount_value;

                    if($usedCoupun >= $coupons->limit)
                    {
                        return redirect()->back()->with('error', __('This coupon code has expired.'));
                    }
                    $price     = $price - $discount_value;
                    $coupon_id = $coupons->id;
                }
                else
                {
                    return redirect()->back()->with('error', __('This coupon code is invalid or has expired.'));
                }
            }

            if($price <= 0)
            {
                $authuser->plan = $plan->id;
                $authuser->save();

                $assignPlan = $authuser->assignPlan($plan->id);

                if($assignPlan['is_success'] == true && !empty($plan))
                {

                    $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
                    Order::create(
                        [
                            'order_id' => $orderID,
                            'name' => null,
                            'email' => null,
                            'card_number' => null,
                            'card_exp_month' => null,
                            'card_exp_year' => null,
                            'plan_name' => $plan->name,
                            'plan_id' => $plan->id,
                            'price' => $price == null ? 0 : $price,
                            'price_currency' => !empty(env('CURRENCY')) ? env('CURRENCY') : 'USD',
                            'txn_id' => '',
                            'payment_type' => 'Tamara',
                            'payment_status' => 'succeeded',
                            'receipt' => null,
                            'user_id' => $authuser->id,
                        ]
                    );
                    $res['msg']  = __("Plan successfully upgraded.");
                    $res['flag'] = 2;

                    return $res;
                }
                else
                {
                    return Utility::error_res(__('Plan fail to upgrade.'));
                }
            }

            $res_data['email']       = Auth::user()->email;
            $res_data['total_price'] = $price;
            $res_data['currency']    = env('CURRENCY');
            $res_data['flag']        = 1;
            $res_data['coupon']      = $coupon_id;

            return $res_data;
        }
        else
        {
            return Utility::error_res(__('Plan is deleted.'));
        }

    }

    public function getPaymentStatus(Request $request, $pay_id, $plan)
    {
        $payment = $this->paymentConfig();
        $planID  = \Illuminate\Support\Facades\Crypt::decrypt($plan);
        $plan    = Plan::find($planID);
        $user    = \Auth::user();
        if($plan)
        {
            try
            {
                if($request->has('coupon_id') && $request->coupon_id != '')
                {

                    if($request->has('coupon_id') && $request->coupon_id != '')
                    {
                        $coupons = Coupon::find($request->coupon_id);
                        if(!empty($coupons))
                        {
                            $userCoupon         = new UserCoupon();
                            $userCoupon->user   = $user->id;
                            $userCoupon->coupon = $coupons->id;
                            $userCoupon->order  = $orderID;
                            $userCoupon->save();


                            $usedCoupun = $coupons->used_coupon();
                            if($coupons->limit <= $usedCoupun)
                            {
                                $coupons->is_active = 0;
                                $coupons->save();
                            }
                        }
                    }

                    $order                 = new Order();
                    $order->order_id       = $orderID;
                    $order->name           = $user->name;
                    $order->card_number    = '';
                    $order->card_exp_month = '';
                    $order->card_exp_year  = '';
                    $order->plan_name      = $plan->name;
                    $order->plan_id        = $plan->id;
                    $order->price          = isset($response->amount) ? $response->amount / 100 : 0;
                    $order->price_currency = env('CURRENCY');
                    $order->txn_id         = isset($response->id) ? $response->id : $pay_id;
                    $order->payment_type   = __('Tamara');
                    $order->payment_status = 'success';
                    $order->receipt        = '';
                    $order->user_id        = $user->id;
                    $order->save();


                    $assignPlan = $user->assignPlan($plan->id, $request->payment_frequency);

                    if($assignPlan['is_success'])
                    {
                        return redirect()->route('plans.index')->with('success', __('Plan activated Successfully!'));
                    }
                    else
                    {
                        return redirect()->route('plans.index')->with('error', __($assignPlan['error']));
                    }
                }
                else
                {
                    return redirect()->route('plans.index')->with('error', __('Transaction has been failed! '));
                }
            }
            catch(\Exception $e)
            {
                return redirect()->route('plans.index')->with('error', __('Plan not found!'));
            }
        }
    }

    public function customerPayWithTamara(Request $request)
    {


        $invoiceID = \Illuminate\Support\Facades\Crypt::decrypt($request->invoice_id);
        $invoice   = Invoice::find($invoiceID);
        $user      = User::find($invoice->created_by);

        $settings=Utility::settingsById($invoice->created_by);

        if($invoice)
        {



            $price = $request->amount;
            if($price > 0)
            {
                $res_data['email']       = $user->email;
                $res_data['total_price'] = $price;
                $res_data['currency']    = isset($settings['site_currency'])?$settings['site_currency']:'USD';
                $res_data['flag']        = 1;

                return $res_data;

            }
            else
            {
                $res['msg']  = __("Enter valid amount.");
                $res['flag'] = 2;

                return $res;
            }

        }
        else
        {
            return redirect()->back()->with('error', __('Invoice is deleted.'));

        }


    }

    public function getInvoicePaymentStatus(Request $request, $pay_id, $invoice_id)
    {
        $invoiceID = \Illuminate\Support\Facades\Crypt::decrypt($invoice_id);
        $invoice   = Invoice::find($invoiceID);
        $this->invoiceData = $invoice;

        $orderID   = strtoupper(str_replace('.', '', uniqid('', true)));
        $settings  = DB::table('settings')->where('created_by', '=', $invoice->created_by)->get()->pluck('value', 'name');
        $payment   = $this->paymentConfig();
        $result    = array();

        if($invoice)
        {
            try
            {

                if($invoiceID)
                {

                    $payments = InvoicePayment::create(
                        [

                            'invoice_id' => $invoice->id,
                            'date' => date('Y-m-d'),
                            'amount' => $request->amount,
                            'payment_method' => 1,
                            'order_id' => $orderID,
                            'payment_type' => __('Tamara'),
                            'receipt' => '',
                            'description' => __('Invoice') . ' ' . Utility::invoiceNumberFormat($settings, $invoice->invoice_id),
                        ]
                    );

                    $invoice = Invoice::find($invoice->id);


                    if($invoice->getDue() <= 0)
                    {
                        Invoice::change_status($invoice->id, 4);
                    }
                    else
                    {
                        Invoice::change_status($invoice->id, 3);
                    }

                    //Slack Notification
                    $setting  = Utility::settings($invoice->created_by);
                    $customer = Customer::find($invoice->customer_id);
                    if(isset($setting['payment_notification']) && $setting['payment_notification'] == 1)
                    {
                        $msg = __("New payment of").' ' . $request->amount . __("created for").' '. $customer->name . __("by").' '. __('Tamara'). '.';
                        Utility::send_slack_msg($msg,$invoice->created_by);
                    }


                    //Telegram Notification
                    $setting  = Utility::settings($invoice->created_by);
                    $customer = Customer::find($invoice->customer_id);
                    if(isset($setting['telegram_payment_notification']) && $setting['telegram_payment_notification'] == 1)
                    {
                        $msg = __("New payment of").' ' . $request->amount . __("created for").' '. $customer->name . __("by").' '. __('Tamara'). '.';
                        Utility::send_telegram_msg($msg,$invoice->created_by);
                    }

                    //Twilio Notification
                    $setting  = Utility::settings($invoice->created_by);
                    $customer = Customer::find($invoice->customer_id);
                    if(isset($setting['twilio_payment_notification']) && $setting['twilio_payment_notification'] ==1)
                    {
                        $msg = __("New payment of").' ' . $request->amount . __("created for").' ' . $customer->name . __("by").' '.  $payments['payment_type'] . '.';
                        Utility::send_twilio_msg($customer->contact,$msg,$invoice->created_by);
                    }




                    return redirect()->route('invoice.link.copy', Crypt::encrypt($invoice->id))->with('success', __(' Payment successfully added.'));
                }
                else
                {
                    return redirect()->route('invoice.link.copy', Crypt::encrypt($invoice->id))->with('error', __('Transaction has been failed! '));
                }
            }
            catch(\Exception $e)
            {

//                dd($e);


                return redirect()->route('invoice.link.copy', Crypt::encrypt($invoice->id))->with('error', __('Invoice not found!'));
            }
        }
    }

    public function payment(Request $request)
    {

        $invoiceID = \Illuminate\Support\Facades\Crypt::decrypt($request->invoice_id);
        $invoice   = Invoice::find($invoiceID);
        $user      = User::find($invoice->created_by);
        $this->invoiceData = $invoice;
        $payment = $this->paymentConfig();
        $YOUR_DOMAIN = url('/');

    // dd($this->tamara_api_code);


        $curl = curl_init();
        $data = [
            "order_reference_id" => "1",
            "order_number" => session('order_id'),
            "total_amount" => [
                "amount" => $request->amount,
                "currency" => "SAR"
            ],
            "description" => "string",
            "country_code" => "SA",
            "payment_type" => "PAY_BY_INSTALMENTS",
            "instalments" => null,
            "locale" => "en_US",
            "items" => [
                [
                    "reference_id" => "123456",
                    "type" => "Digital",
                    "name" => "Lego City 8601",
                    "sku" => "SA-12436",
                    "image_url" => "https://www.example.com/product.jpg",
                    "item_url" => "https://www.example.com/product.html",
                    "quantity" => 1,
                    "unit_price" => [
                        "amount" => "100.00",
                        "currency" => "SAR"
                    ],
                    "discount_amount" => [
                        "amount" => "100.00",
                        "currency" => "SAR"
                    ],
                    "tax_amount" => [
                        "amount" => "100.00",
                        "currency" => "SAR"
                    ],
                    "total_amount" => [
                        "amount" => "100.00",
                        "currency" => "SAR"
                    ]
                ]
            ],
            "consumer" => [
                "first_name" => "Mona",
                "last_name" => "Lisa",
                "phone_number" => "502223333",
                "email" => "user@example.com"
            ],
            "billing_address" => [
                "first_name" => "Mona",
                "last_name" => "Lisa",
                "line1" => "3764 Al Urubah Rd",
                "line2" => "string",
                "region" => "As Sulimaniyah",
                "postal_code" => "12345",
                "city" => "Riyadh",
                "country_code" => "SA",
                "phone_number" => "502223333"
            ],
            "shipping_address" => [
                "first_name" => "Mona",
                "last_name" => "Lisa",
                "line1" => "3764 Al Urubah Rd",
                "line2" => "string",
                "region" => "As Sulimaniyah",
                "postal_code" => "12345",
                "city" => "Riyadh",
                "country_code" => "SA",
                "phone_number" => "502223333"
            ],
            "discount" => [
                "name" => "Christmas 2020",
                "amount" => [
                    "amount" => "100.00",
                    "currency" => "SAR"
                ]
            ],
            "tax_amount" => [
                "amount" => "100.00",
                "currency" => "SAR"
            ],
            "shipping_amount" => [
                "amount" => "100.00",
                "currency" => "SAR"
            ],
            "merchant_url" => [
                'success' => $YOUR_DOMAIN . '/pay-tamara/success',
                'cancel' => url()->previous(),
                "failure"=>  $YOUR_DOMAIN . '/pay-tamara/fail',
                "notification" => "https://example.com/payments/tamarapay"

            ],
            "platform" => "Magento",
            "is_mobile" => false,
            "risk_assessment" => [
                "customer_age" => 22,
                "customer_dob" => "31-01-2000",
                "customer_gender" => "Male",
                "customer_nationality" => "SA",
                "is_premium_customer" => true,
                "is_existing_customer" => true,
                "is_guest_user" => true,
                "account_creation_date" => "31-01-2019",
                "platform_account_creation_date" => "string",
                "date_of_first_transaction" => "31-01-2019",
                "is_card_on_file" => true,
                "is_COD_customer" => true,
                "has_delivered_order" => true,
                "is_phone_verified" => true,
                "is_fraudulent_customer" => true,
                "total_ltv" => 501.5,
                "total_order_count" => 12,
                "order_amount_last3months" => 301.5,
                "order_count_last3months" => 2,
                "last_order_date" => "31-01-2021",
                "last_order_amount" => 301.5,
                "reward_program_enrolled" => true,
                "reward_program_points" => 300
            ],
            "expires_in_minutes" => 0,
            "additional_data" => [
                "delivery_method" => "home delivery",
                "pickup_store" => "Store A",
                "store_code" => "Store code A",
                "vendor_amount" => 0,
                "merchant_settlement_amount" => 0,
                "vendor_reference_code" => "string"
            ]
        ];

        $jsonData = json_encode($data);


        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.tamara.co/checkout',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . $this->tamara_api_code,
                'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);

        // dd($this->tamara_api_code);

        curl_close($curl);

        $responseArray = json_decode($response, true);

        $webUrl = $responseArray['checkout_url'];


        return redirect()->away($webUrl);




    }

}
