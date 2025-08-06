<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Token;
use App\Models\User;
use App\Models\StudentChoice;
use App\Models\PaymentTransaction;
use App\Models\PaymentSpotTransaction;
use App\Models\SpotStudent;
use App\Models\Payment;
use App\Models\Fees;
use Exception;
use Validator;
use DB;
use App\PaymentLib\AESEncDec;
use Illuminate\Support\Carbon;


use App\Http\Resources\StudentChoiceResource;

class PaymentController extends Controller
{

    protected $auth;
    public $back_url = null;

    public function __construct()
    {
        //$this->auth = new Authentication();
    }

    //Payment 
    public function AdmissionPaymentFees(Request $request)
    {
        $student_array = $request->input('student_info');

        // Ensure it's a non-empty array and get the first element
        if (!is_array($student_array) || empty($student_array[0])) {
            return response()->json([
                'error' => true,
                'message' => 'Student info is missing or invalid.'
            ], 400);
        }

        // Extract the single student data
        $student_data = $student_array[0];

        // Validate required keys
        $requiredKeys = ['inst_code', 'trade_code', 'student_payment_for', 'appl_form_num'];
        foreach ($requiredKeys as $key) {
            if (empty($student_data[$key])) {
                return response()->json([
                    'error' => true,
                    'message' => "Missing or empty required field: {$key}"
                ], 400);
            }
        }

        // Get fee amount
        $total_appl_amount = Fees::select('cf_fees_amount')
            ->where('cf_fees_type', 'APPLICATION')
            ->first();

        if (!$total_appl_amount || $total_appl_amount->cf_fees_amount <= 0) {
            return response()->json([
                'error' => true,
                'message' => 'Application fee amount not found or invalid.'
            ], 500);
        }

        $amount = $total_appl_amount->cf_fees_amount;

        $other_data = "{$student_data['inst_code']}_{$student_data['trade_code']}_{$student_data['student_payment_for']}_{$student_data['appl_form_num']}_{$amount}";

        // Generate a random 10-character order ID
        $orderid = '';
        for ($i = 0; $i < 10; $i++) {
            $d = rand(1, 30) % 2;
            $d = $d ? chr(rand(65, 90)) : chr(rand(48, 57));
            $orderid .= $d;
        }
        $student_check = PaymentTransaction::where('pmnt_stud_id', $student_data['appl_form_num'])
            ->where('pmnt_pay_type', $student_data['student_payment_for'])
            ->exists();

        if ($student_check) {
            return response()->json([
                'error' => true,
                'message' => 'Payment transaction already exists for this student.'
            ], 400);
        } else {

            // Create new payment transaction
            PaymentTransaction::create([
                'order_id' => $orderid,
                'pmnt_modified_by' => $student_data['appl_form_num'],
                'trans_time' => now(),
                'pmnt_created_on' => now(),
                'pmnt_pay_type' => $student_data['student_payment_for'],
                'trans_amount' => $amount,
                'pmnt_stud_id' => $student_data['appl_form_num']
            ]);
        }


        auditTrail($student_data['appl_form_num'], "Payment initiated by student: {$student_data['appl_form_num']} with order id: {$orderid} for {$student_data['student_payment_for']}");

        return response()->json([
            'error' => false,
            'message' => 'Payment Data Found',
            'payment_data' => getPaymentData($orderid, $amount, $other_data)
        ]);
    }


    public function paymentSuccess(Request $request)
    {

        // Merchant Order Number|SBIePayRefID/ATRN|Transaction Status|Amount|Currency|Pay Mode|Other Details|Reason/Message|Bank Code|Bank Reference Number|Transaction Date|Country|CIN|Merchant ID|Total Fee GST |Ref1|Ref2|Ref3|Ref4|Ref5|Ref6|Ref7|Ref8|Ref9
        try {
            $trans_details = sbiDecrypt($request->encData);
            $data = explode('|', $trans_details);
            $order_id = $data[0];
            $trans_id = $data[1];
            $trans_status = $data[2];
            $trans_amount = $data[3];
            $currency = $data[4];
            $trans_mode = $data[5];
            $message = $data[7];
            $trans_time = $data[10];
            $marchnt_id = $data[13];
            $other_data = explode('_', $data[5]);


            $inst_code = $other_data[0];
            $course_code = $other_data[1];
            $paying_for = $other_data[2];
            $form_num = $other_data[3];
            $amount = $other_data[4];
            $map = [
                'APPLICATION' => 1,
            ];

            $status = $map[$paying_for] ?? null;

            if ($status !== null) {
                Student::where([
                    'student_form_num' => $form_num,
                    // 'student_semester' => $semester,
                ])->update([
                    's_admited_status' => $status
                ]);
            }
            $tranction = PaymentTransaction::where('order_id', $order_id)->first();

            if ($tranction) {
                $tranction->update([
                    'trans_id' => $trans_id,
                    'trans_status' => $trans_status,
                    'trans_amount' => $trans_amount,
                    'trans_mode' => $trans_mode,
                    'trans_time' => $trans_time,
                    'marchnt_id' => $marchnt_id,
                    'trans_details' => $trans_details,
                    'is_verified' => 1,

                ]);

                Payment::create([
                    'order_id' => $order_id,
                    'trans_id' => $trans_id,
                    'paid_type' => $paying_for,
                    'paid_amount' => $trans_amount,
                    'paid_at' => $trans_time,
                    'payment_mode' => $trans_mode,
                    'detail' => $trans_details,
                    'form_no' =>  $form_num
                ]);

                Student::where('s_appl_form_num', $form_num)
                    ->update([
                        'is_payment' => 1
                    ]);

                auditTrail($form_num, "Payment {$trans_status} for Application No: {$form_num}, ORDER ID: {$order_id}, TRANSACTION ID: {$trans_id}");

                return redirect()->route('payment.redirect', [
                    'trans_id' => $trans_id,
                    'order_id' => $order_id,
                    'paying_for' => $paying_for,
                    'message' => $message,
                    'currency' => $currency,
                    'trans_amount' => $trans_amount,
                    'trans_time' => date('d-m-Y h:i a', strtotime($trans_time)),
                    'trans_status' => $trans_status,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ]);
        }
    }
    public function paymentFail(Request $request)
    {
        // Merchant Order Number|SBIePayRefID/ATRN|Transaction Status|Amount|Currency|Pay Mode|Other Details|Reason/Message|Bank Code|Bank Reference Number|Transaction Date|Country|CIN|Merchant ID|Total Fee GST |Ref1|Ref2|Ref3|Ref4|Ref5|Ref6|Ref7|Ref8|Ref9
        try {
            $trans_details = sbiDecrypt($request->encData);
            $data = explode('|', $trans_details);
            $order_id = $data[0];
            $trans_id = $data[1];
            $trans_status = $data[2];
            $trans_amount = $data[3];
            $currency = $data[4];
            $trans_mode = $data[5];
            $message = $data[7];
            $trans_time = $data[10];
            $marchnt_id = $data[13];
            $other_data = explode('_', $data[5]);


            $inst_code = $other_data[0];
            $course_code = $other_data[1];
            $paying_for = $other_data[2];
            $form_num = $other_data[3];
            $amount = $other_data[4];
            $tranction = PaymentTransaction::where('order_id', $order_id)->first();

            if ($tranction) {
                $tranction->update([
                    'trans_id' => $trans_id,
                    'trans_status' => $trans_status,
                    'trans_amount' => $trans_amount,
                    'trans_mode' => $trans_mode,
                    'trans_time' => $trans_time,
                    'marchnt_id' => $marchnt_id,
                    'trans_details' => $trans_details,
                    'is_verified' => 1,
                ]);

                auditTrail($form_num, "Payment {$trans_status} for Application No: {$form_num}, ORDER ID: {$order_id}, TRANSACTION ID: {$trans_id}");
                return redirect()->route('payment.redirect', [
                    'trans_id' => $trans_id,
                    'order_id' => $order_id,
                    'paying_for' => $paying_for,
                    'message' => $message,
                    'currency' => $currency,
                    'trans_amount' => $trans_amount,
                    'trans_time' => date('d-m-Y h:i a', strtotime($trans_time)),
                    'trans_status' => $trans_status,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ]);
        }
    }
    public function paymentPush(Request $request)
    {
        // Merchant Order Number|SBIePayRefID/ATRN|Transaction Status|Amount|Currency|Pay Mode|Other Details|Reason/Message|Bank Code|Bank Reference Number|Transaction Date|Country|CIN|Merchant ID|Total Fee GST |Ref1|Ref2|Ref3|Ref4|Ref5|Ref6|Ref7|Ref8|Ref9

        $trans_details = sbiDecrypt($request->encData);
        $data = explode('|', $trans_details);
        $order_id = $data[0];
        $trans_id = $data[1];
        $trans_status = $data[2];
        $trans_amount = $data[3];
        $currency = $data[4];
        $trans_mode = $data[5];
        $message = $data[7];
        $trans_time = $data[10];
        $marchnt_id = $data[13];
        $other_data = explode('_', $data[5]);


        $inst_code = $other_data[0];
        $course_code = $other_data[1];
        $paying_for = $other_data[2];
        $form_num = $other_data[3];
        $amount = $other_data[4];

        $tranction = PaymentTransaction::where('order_id', $order_id)->first();

        if ($tranction) {
            $tranction->update([
                'trans_id' => $trans_id,
                'trans_status' => $trans_status,
                'trans_amount' => $trans_amount,
                'trans_mode' => $trans_mode,
                'trans_time' => $trans_time,
                'marchnt_id' => $marchnt_id,
                'trans_details' => $trans_details,
                'is_verified' => 1,
            ]);
            auditTrail($form_num, "Payment {$trans_status} for Application No: {$form_num}, ORDER ID: {$order_id}, TRANSACTION ID: {$trans_id}");

            return redirect()->route('payment.redirect', [
                'trans_id' => $trans_id,
                'order_id' => $order_id,
                'paying_for' => $paying_for,
                'message' => $message,
                'currency' => $currency,
                'trans_amount' => $trans_amount,
                'trans_time' => date('d-m-Y h:i a', strtotime($trans_time)),
                'trans_status' => $trans_status,
            ]);
        }
    }

    //allotment upgrade payment
    public function paymentUpgrade(Request $request)
    {
        $trans_time = date('Y-m-d H:i:s');
        $validated = Validator::make($request->all(), [
            'student_id' => ['required'],
        ]);

        if ($validated->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validated->errors()
            ]);
        }

        $orderid = '';
        for ($i = 0; $i < 10; $i++) {
            $d = rand(1, 30) % 2;
            $d = $d ? chr(rand(65, 90)) : chr(rand(48, 57));
            $orderid = $orderid . $d;
        }

        $merchIdVal = env('SBI_MERCHANT_ID');
        $base_url = env('APP_URL') . '/payment/';
        $success_url = $base_url . 'upgrade-success';
        $fail_url = $base_url . 'upgrade-fail';
        $key = env('SBI_PAYMENT_KEY');
        $other = "COUNSELLINGUPGRADEFEES_" . $request->student_id;
        $marid =  '5';
        $merchant_order_num = $orderid;
        $total_amount = env('COUNSELLING_UPGRADE_FEES');
        $requestParameter  = "{$merchIdVal}|DOM|IN|INR|" . $total_amount . "|" . $other . "|" . $success_url . "|" . $fail_url . "|SBIEPAY|" . $merchant_order_num . "|" . $marid . "|NB|ONLINE|ONLINE";

        $aes =  new AESEncDec();
        $EncryptTrans = $aes->encrypt($requestParameter, $key);

        //$merchIdVal = env('SBI_MERCHANT_ID');
        $actionUrl = env('SBI_PAYMENT_API');

        $user_data = User::where('s_id', $request->student_id)->first();
        $choice_list = StudentChoice::where('ch_stu_id', $request->student_id)->where('is_alloted', 1)->with('student')->first();
        $inst_code = $choice_list->ch_inst_code;
        $trade_code = $choice_list->ch_trade_code;
        $choice_pref_no = $choice_list->ch_pref_no;
        $allotment_category = $choice_list->ch_alloted_category;
        $alloted_round = $choice_list->ch_alloted_round;
        $choice_id = $choice_list->ch_id;
        //DB::beginTransaction();
        //try {
        $user_data->update([
            'updated_at' => now(),
        ]);

        PaymentTransaction::create([
            'order_id' => $orderid,
            'pmnt_modified_by' => $request->student_id,
            'pmnt_stud_id' => $request->student_id,
            'pmnt_created_on' => $trans_time,
            'trans_amount' => intval($total_amount),
            'pmnt_pay_type' => 'COUNSELLINGUPGRADEFEES'
        ]);

        auditTrail($request->student_id, "Payment for upgrade initiated for order ID {$orderid}");
        studentActivite($request->student_id, "Payment for upgrade initiated for order ID {$orderid}");

        $reponse = array(
            'error'         =>  false,
            'message'       =>  'Payment Data',
            'EncryptTrans' => $EncryptTrans,
            'merchIdVal' => $merchIdVal,
            'actionUrl' => $actionUrl
        );
        return response(json_encode($reponse), 200);
    }

    public function paymentUpgradeSuccess(Request $request)
    {
        $key = env('SBI_PAYMENT_KEY');
        $aes =  new AESEncDec();
        $trans_details = $aes->decrypt($request->encData, $key);

        $data = explode('|', $trans_details);

        $order_id        =    $data[0]; // D3UGOGS1D2
        $trans_id        =    $data[1]; // 4984477659512
        $trans_status    =    $data[2]; // SUCCESS
        $trans_amount        =    $data[3]; // 500
        $currency        =    $data[4]; // INR
        $trans_mode        =    $data[5]; // NB
        $stu_data        =    explode('_', $data[6]); // OD
        $trans_time        =    $data[10]; // 2024-05-09 14:41:52
        $country_code        =    $data[11]; // IN
        $marchnt_id        =    $data[13]; // 1000605
        $bank_code = $data[8];             // SBIN
        $bank_ref_no = $data[9];           // 415228230701

        $payment_data = PaymentTransaction::where('order_id', $order_id)->first();

        if ($payment_data) {
            $payment_data->update([
                'trans_id' => $trans_id,
                'trans_status' => $trans_status,
                'trans_amount' => $trans_amount,
                'trans_mode' => $trans_mode,
                'trans_time' => $trans_time,
                'country_code' => $country_code,
                'marchnt_id' => $marchnt_id,
                'trans_details' => $trans_details,
                'bank_code' => $bank_code,
                'bank_ref' => $bank_ref_no,
                'pmnt_pay_type' => $stu_data[0],
                'pmnt_modified_by' => $stu_data[1],

            ]);
        }
        $user_id = $stu_data[1];
        $user_data = User::where('s_id', $user_id)->first();
        $student_name = $user_data->s_candidate_name;

        $user_data->update([
            'updated_at' => now(),
            'is_upgrade_payment' => 1
        ]);

        studentActivite($user_id, "Payment for choice upgradation was {$trans_status} for {$student_name} having Order ID {$order_id}");

        auditTrail($user_id, "Payment for choice upgradation was {$trans_status} for {$student_name} having Order ID {$order_id}");

        return redirect()->route('upgrade-payment-success-redirect', $trans_id);
    }

    public function paymentUpgradeFail(Request $request)
    {
        $trans_time = date('Y-m-d H:i:s');
        $key = env('SBI_PAYMENT_KEY');
        $aes =  new AESEncDec();
        $decrypt = $aes->decrypt($request->encData, $key);
        $data = explode('|', $decrypt);
        $user_id = explode('_', $data[6]);
        $order_id        =    $data[0];

        $payment_data = PaymentTransaction::where('order_id', $order_id)->first();
        if ($payment_data) {
            $payment_data->update([
                'trans_details' => $decrypt,
                'trans_time' => $trans_time
            ]);
        }

        $message = "Payment failed for order ID {$order_id}";
        auditTrail($request->student_id, $message);
        studentActivite($request->student_id, $message);

        return redirect()->route('upgrade-payment-fail', $user_id[1]);
    }

    //Verify payment - double verification
    public function verifypayment(Request $request)
    {
        $currentDateTime    =   date('Y-m-d H:i:s');
        $startToday         =   date('Y-m-d 00:00:01');
        $previousOneHour    =   Carbon::parse($currentDateTime)->subHour(1)->format('Y-m-d H:i:s');
        //dd($previousOneHour);
        // $allData = PaymentTransaction::where('trans_time', '<=', $previousOneHour)->where('trans_time', '>=', $startToday)->get();
        // return $allData;

        $allData = PaymentTransaction::where('trans_time', '<=', $previousOneHour)->where('trans_time', '>=', $startToday)->chunk(25, function ($people) {
            foreach ($people as $person) {
                $order_id = $person->order_id;
                $marchnt_id     = '1001954';
                $trans_amount   = '500';
                //Two step verification 
                $merchant_order_no = $order_id; // merchant order no
                $merchantid = $marchnt_id;  //merchant id
                $amount = $trans_amount;
                $url = "https://www.sbiepay.sbi/payagg/statusQuery/getStatusQuery"; // double verification url
                $queryRequest = "|$merchantid| $merchant_order_no|$amount";
                $queryRequest33 = http_build_query(array('queryRequest' => $queryRequest, "aggregatorId" => "SBIEPAY", "merchantId" => $merchantid));

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_SSLVERSION, true);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $queryRequest33);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    echo $error_msg = curl_error($ch);
                }

                curl_close($ch);
                $decrypt = $response;
                $data = explode('|', $decrypt);

                if ($data[2] == "SUCCESS") {
                    $payment_data = PaymentTransaction::where('order_id', $order_id)->first();

                    $user_data = User::where('s_id', $person->pmnt_modified_by)->first();

                    $type = explode('|', $data[5]);
                    if ($payment_data) {
                        $payment_data->update([
                            'trans_id' => $data[1],
                            'trans_status' => $data[2],
                            'trans_amount' => $data[7],
                            'trans_mode' => $data[12],
                            'trans_time' => $data[11],
                            'country_code' => $data[3],
                            'marchnt_id' => $marchnt_id,
                            'trans_details' => $response,
                            'bank_code' => $data[9],
                            'bank_ref' => $data[10],
                            'pmnt_pay_type' => $type[0],
                            'pmnt_modified_by' => $person->pmnt_modified_by,
                        ]);

                        $user_data->update([
                            'is_payment' => 1
                        ]);
                    }
                }
            }
        });

        echo 'Updated';
    }

    //Spot Registration payment
    public function paymentSpot(Request $request)
    {
        $trans_time = date('Y-m-d H:i:s');
        $validated = Validator::make($request->all(), [
            'student_id' => ['required'],
        ]);

        if ($validated->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validated->errors()
            ]);
        }

        $orderid = '';
        for ($i = 0; $i < 10; $i++) {
            $d = rand(1, 30) % 2;
            $d = $d ? chr(rand(65, 90)) : chr(rand(48, 57));
            $orderid = $orderid . $d;
        }
        $merchIdVal = env('SBI_MERCHANT_ID');
        $base_url = env('APP_URL') . '/payment/';
        $success_url = $base_url . 'success-spot';
        $fail_url = $base_url . 'fail-spot';
        $key = env('SBI_PAYMENT_KEY');
        $other            =    "REGISTRATIONFEES_" . $request->student_id;
        $marid =  '5';
        $merchant_order_num = $orderid;
        $total_amount = env('REGISTRATION_FEES');
        $requestParameter  = "{$merchIdVal}|DOM|IN|INR|" . $total_amount . "|" . $other . "|" . $success_url . "|" . $fail_url . "|SBIEPAY|" . $merchant_order_num . "|" . $marid . "|NB|ONLINE|ONLINE";

        $aes =  new AESEncDec();
        $EncryptTrans = $aes->encrypt($requestParameter, $key);


        $actionUrl = env('SBI_PAYMENT_API');

        $reponse = array(
            'error'         =>  false,
            'message'       =>  'Payment Data',
            'EncryptTrans' => $EncryptTrans,
            'merchIdVal' => $merchIdVal,
            'actionUrl' => $actionUrl
        );

        PaymentSpotTransaction::create([
            'order_id' => $orderid,
            'pmnt_modified_by' => $request->student_id,
            'pmnt_stud_id' => $request->student_id,
            'pmnt_created_on' => $trans_time,
            'trans_amount' => intval($total_amount),
            'pmnt_pay_type' => 'REGISTRATIONFEES'
        ]);


        $message = "Payment initiated for order ID {$orderid}";
        auditTrail($request->student_id, $message);
        studentActivite($request->student_id, $message);

        return response(json_encode($reponse), 200);
    }

    //success spot Registration
    public function paymentSuccessSpot(Request $request)
    {
        $key = env('SBI_PAYMENT_KEY');
        $aes =  new AESEncDec();
        $trans_details = $aes->decrypt($request->encData, $key);

        $data = explode('|', $trans_details);

        $order_id        =    $data[0]; // D3UGOGS1D2
        $trans_id        =    $data[1]; // 4984477659512
        $trans_status    =    $data[2]; // SUCCESS
        $trans_amount        =    $data[3]; // 500
        $currency        =    $data[4]; // INR
        $trans_mode        =    $data[5]; // NB
        $stu_data        =    explode('_', $data[6]); // OD
        $trans_time        =    $data[10]; // 2024-05-09 14:41:52
        $country_code        =    $data[11]; // IN
        $marchnt_id        =    $data[13]; // 1000605
        $bank_code = $data[8];             // SBIN
        $bank_ref_no = $data[9];           // 415228230701

        $payment_data = PaymentSpotTransaction::where('order_id', $order_id)->first();
        if ($payment_data) {
            $payment_data->update([
                'trans_id' => $trans_id,
                'trans_status' => $trans_status,
                'trans_amount' => $trans_amount,
                'trans_mode' => $trans_mode,
                'trans_time' => $trans_time,
                'country_code' => $country_code,
                'marchnt_id' => $marchnt_id,
                'trans_details' => $trans_details,
                'bank_code' => $bank_code,
                'bank_ref' => $bank_ref_no,
                'pmnt_pay_type' => $stu_data[0],
                'pmnt_modified_by' => $stu_data[1],
            ]);
        }

        $user_id = $stu_data[1];
        $user_data = SpotStudent::where('s_id', $user_id)->first();
        $student_name = $user_data->s_candidate_name;

        auditTrail($user_id, "Payment {$trans_status} for {$student_name} whose order ID is {$order_id}");
        studentActivite($user_id, "Payment {$trans_status} for {$student_name} whose order ID is {$order_id} for spot registration fees of amount {$trans_amount}");

        $user_data->update([
            'updated_at' => now(),
            'is_payment' => 1
        ]);

        return redirect()->route('payment-success-spot-redirect', $trans_id);
    }

    //failed spot Registration
    public function paymentFailSpot(Request $request)
    {
        $key = env('SBI_PAYMENT_KEY');
        $aes =  new AESEncDec();
        $decrypt = $aes->decrypt($request->encData, $key);
        $data = explode('|', $decrypt);
        $user_id = explode('_', $data[6]);
        $order_id        =    $data[0];
        $trans_time = date('Y-m-d H:i:s');

        $payment_data = PaymentSpotTransaction::where('order_id', $order_id)->first();
        if ($payment_data) {
            $payment_data->update([
                'trans_details' => $decrypt,
                'trans_time' => $trans_time
            ]);
        }

        $message = "Payment failed for order ID {$order_id}";
        auditTrail($request->student_id, $message);
        studentActivite($request->student_id, $message);

        return redirect()->route('payment-fail-spot', $user_id[1]);
    }

    //Spot Counselling payment
    public function paymentSpotCounselling(Request $request)
    {
        $trans_time = date('Y-m-d H:i:s');
        $validated = Validator::make($request->all(), [
            'student_id' => ['required'],
        ]);

        if ($validated->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validated->errors()
            ]);
        }

        $orderid = '';
        for ($i = 0; $i < 10; $i++) {
            $d = rand(1, 30) % 2;
            $d = $d ? chr(rand(65, 90)) : chr(rand(48, 57));
            $orderid = $orderid . $d;
        }
        $merchIdVal = env('SBI_MERCHANT_ID');
        $base_url = env('APP_URL') . '/payment/';
        $success_url = $base_url . 'success-spot-counselling';
        $fail_url = $base_url . 'fail-spot-counselling';
        $key = env('SBI_PAYMENT_KEY');
        $other            =    "COUNSELLINGSPOTFEES_" . $request->student_id;
        $marid =  '5';
        $merchant_order_num = $orderid;
        $total_amount = env('COUNSELLING_FEES');
        $requestParameter  = "{$merchIdVal}|DOM|IN|INR|" . $total_amount . "|" . $other . "|" . $success_url . "|" . $fail_url . "|SBIEPAY|" . $merchant_order_num . "|" . $marid . "|NB|ONLINE|ONLINE";

        $aes =  new AESEncDec();
        $EncryptTrans = $aes->encrypt($requestParameter, $key);


        $actionUrl = env('SBI_PAYMENT_API');

        $reponse = array(
            'error'         =>  false,
            'message'       =>  'Payment Data',
            'EncryptTrans' => $EncryptTrans,
            'merchIdVal' => $merchIdVal,
            'actionUrl' => $actionUrl
        );

        PaymentSpotTransaction::create([
            'order_id' => $orderid,
            'pmnt_modified_by' => $request->student_id,
            'pmnt_stud_id' => $request->student_id,
            'pmnt_created_on' => $trans_time,
            'trans_amount' => intval($total_amount),
            'pmnt_pay_type' => 'COUNSELLINGSPOTFEES'
        ]);


        $message = "Payment initiated for order ID {$orderid}";
        auditTrail($request->student_id, $message);
        studentActivite($request->student_id, $message);

        return response(json_encode($reponse), 200);
    }

    //success spot Counselling
    public function paymentSuccessSpotCounselling(Request $request)
    {
        $key = env('SBI_PAYMENT_KEY');
        $aes =  new AESEncDec();
        $trans_details = $aes->decrypt($request->encData, $key);

        $data = explode('|', $trans_details);

        $order_id        =    $data[0]; // D3UGOGS1D2
        $trans_id        =    $data[1]; // 4984477659512
        $trans_status    =    $data[2]; // SUCCESS
        $trans_amount        =    $data[3]; // 500
        $currency        =    $data[4]; // INR
        $trans_mode        =    $data[5]; // NB
        $stu_data        =    explode('_', $data[6]); // OD
        $trans_time        =    $data[10]; // 2024-05-09 14:41:52
        $country_code        =    $data[11]; // IN
        $marchnt_id        =    $data[13]; // 1000605
        $bank_code = $data[8];             // SBIN
        $bank_ref_no = $data[9];           // 415228230701

        $payment_data = PaymentSpotTransaction::where('order_id', $order_id)->first();
        if ($payment_data) {
            $payment_data->update([
                'trans_id' => $trans_id,
                'trans_status' => $trans_status,
                'trans_amount' => $trans_amount,
                'trans_mode' => $trans_mode,
                'trans_time' => $trans_time,
                'country_code' => $country_code,
                'marchnt_id' => $marchnt_id,
                'trans_details' => $trans_details,
                'bank_code' => $bank_code,
                'bank_ref' => $bank_ref_no,
                'pmnt_pay_type' => $stu_data[0],
                'pmnt_modified_by' => $stu_data[1],
            ]);
        }

        $user_id = $stu_data[1];
        $user_data = SpotStudent::where('s_id', $user_id)->first();
        $student_name = $user_data->s_candidate_name;

        auditTrail($user_id, "Payment {$trans_status} for {$student_name} whose order ID is {$order_id}");
        studentActivite($user_id, "Payment {$trans_status} for {$student_name} whose order ID is {$order_id} for spot counselling fees of amount {$trans_amount}");

        $user_data->update([
            'updated_at' => now(),
            'is_counselling_fees' => 1
        ]);

        return redirect()->route('payment-success-spot-counselling-redirect', $trans_id);
    }

    //failed spot Counselling
    public function paymentFailSpotCounselling(Request $request)
    {
        $key = env('SBI_PAYMENT_KEY');
        $aes =  new AESEncDec();
        $decrypt = $aes->decrypt($request->encData, $key);
        $data = explode('|', $decrypt);
        $user_id = explode('_', $data[6]);
        $order_id        =    $data[0];
        $trans_time = date('Y-m-d H:i:s');

        $payment_data = PaymentSpotTransaction::where('order_id', $order_id)->first();
        if ($payment_data) {
            $payment_data->update([
                'trans_details' => $decrypt,
                'trans_time' => $trans_time
            ]);
        }

        $message = "Payment failed for order ID {$order_id}";
        auditTrail($request->student_id, $message);
        studentActivite($request->student_id, $message);

        return redirect()->route('payment-fail-spot-counselling', $user_id[1]);
    }
}
