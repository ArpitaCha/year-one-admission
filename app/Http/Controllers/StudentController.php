<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Token;
use App\Http\Resources\StudentResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Institute;
use App\Models\Trade;
use App\Models\PaymentTransaction;
use App\Models\JexpoApplElgbExam;
use App\Models\Role;
use App\Models\State;
use App\Models\District;
use App\Models\Subdivision;



class StudentController extends Controller
{

    protected $auth;
    public $back_url = null;

    public function __construct()
    {
        //$this->auth = new Authentication();
    }
    public function getStudentInfo(Request $request, $form_num)
    {
        $student = Student::where('s_appl_form_num', $form_num)->with('trade:t_id,t_code,t_name', 'institute:i_id,i_code,i_name', 'state:state_id_pk,state_name', 'district:district_id_pk,district_name', 'subdivision:id,name')->first();
        if ($student) {
            $reponse = array(
                'error'                 =>  false,
                'message'               =>  'Data Found',
                'student' =>   new StudentResource($student)
            );
            return response(json_encode($reponse), 200);
        } else {
            $reponse = array(
                'error'     =>  true,
                'message'   =>  'No Student available'
            );
            return response(json_encode($reponse), 200);
        }
    }
    public function studentDetails(Request $request, $form_num)
    {
        $now    =   date('Y-m-d H:i:s');
        $today  =   date('Y-m-d');
        $time   =   date('H:i:s');

        try {
            $random = env('ENC_KEY');
            $check_student = Student::with('trade:t_id,t_code,t_name', 'institute:i_id,i_code,i_name', 'state:state_id_pk,state_name', 'district:district_id_pk,district_name', 'subdivision:id,name')->where('s_appl_form_num', $form_num)
                ->first();
            $qualification = JexpoApplElgbExam::where('exam_appl_form_num', $form_num)
                ->first();
            $status = $msg = $message = "";
            if (!$check_student) {
                return response()->json([
                    'error'     =>  true,
                    'message'   => 'NO record Found'
                ],  200);
            }
            $message = "Data fetched successfully";
            $data = [
                'student_appl_form_num' => $check_student->s_appl_form_num,
                'student_first_name' => $check_student->s_first_name,
                'student_middle_name' => $check_student->s_middle_name,
                'student_last_name' => $check_student->s_last_name,
                'student_father_name' => $check_student->s_father_name,
                'student_mother_name' => $check_student->s_mother_name,
                'student_dob' => $check_student->s_dob,
                'student_state' => [
                    'state_id'   => $check_student->s_state_id ?? '',
                    'state_name' => $check_student->state->state_name ?? '',
                ],
                'student_trade_code' => [
                    'trade_code'   => $check_student->s_trade_code ?? '',
                    'trade_name' => $check_student->trade->t_name ?? '',
                ],
                'student_inst_code' => [
                    'inst_code'   => $check_student->s_inst_code ?? '',
                    'inst_name' => $check_student->institute->i_name ?? '',
                ],
                'student_district' => [
                    'district_id'   => $check_student->s_home_district ?? '',
                    'district_name' => $check_student->district->district_name ?? '',
                ],
                'student_subdivision' => [
                    'subdivision_id'   => $check_student->s_subdivision ?? '',
                    'subdivision_name' => $check_student->subdivision->name ?? '',
                ],
                'student_email' => $check_student->s_email,
                'student_gender' => $check_student->s_gender,
                'student_religion' => $check_student->s_religion,
                'student_caste' => $check_student->s_caste,
                'student_tfw' => $check_student->s_tfw,
                'student_adharno' => decryptHEXFormat($check_student->s_aadhar_no),
                'student_address' => $check_student->s_address,
                'student_address2' => $check_student->s_address2,
                'student_pin_no' => $check_student->s_pin_no,
                'student_phone' => $check_student->s_phone,
                'student_guardian_name' => $check_student->s_guardian_name,
                'student_is_married' => $check_student->is_married,
                'student_pwd' => $check_student->s_pwd,
                'student_photo' => $check_student->s_photo,
                'student_sign' => $check_student->s_sign,
                'student_citizenship' => $check_student->s_citizenship,
                'student_kanyashree' => $check_student->s_kanyashree,
                'exam_board'     => $qualification->exam_board,
                'exam_pass_yr'   => $qualification->exam_pass_yr,
                'exam_tot_marks' => $qualification->exam_tot_marks,
                'exam_ob_marks'  => $qualification->exam_ob_marks,
                'exam_result'    => $qualification->exam_result,
                'exam_elgb_code' => $qualification->exam_elgb_code,
                'eng_marks' => $qualification->eng_marks,
                'msg' => $msg,
            ];

            // dd($data);
            return response()->json([
                'error'     =>  false,
                'message'   =>  $message,
                'data'  =>  $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'error'     =>  true,
                'message'   =>  $e->getMessage()
            ], 400);
        }
    }

    public function studentInfoUpdate(Request $request)
    {
        if ($request->header('token')) {
            $now    =   date('Y-m-d H:i:s');
            $token_check = Token::where('t_token', '=', $request->header('token'))->where('t_expired_on', '>=', $now)->first();
            if ($token_check) {
                $user_id = $token_check->t_user_id;
                $user_data = Student::select('u_role_id')->where('s_id', $user_id)->first();
                $role_url_access_id = DB::table('jexpo_auth_roles_permissions')->where('rp_role_id', $user_data->u_role_id)->pluck('rp_url_id');
                if (sizeof($role_url_access_id) > 0) {
                    $urls = DB::table('jexpo_auth_urls')->where('url_visible', 1)->whereIn('url_id', $role_url_access_id)->get()->toArray();
                    $url_data = array_column($urls, 'url_name');
                    if (in_array('student-update', $url_data)) {
                        $validator = Validator::make($request->all(), [
                            'student_inst_id' => ['required'],
                            'student_first_name' => ['required'],
                            'student_last_name' => ['required'],
                            'student_father_name' => ['required'],
                            'student_mother_name' => ['required'],
                            'student_guardian_name' => ['required'],
                            'student_dob' => ['required'],
                            'student_aadhar_no' => ['required', 'unique:jexpo_register_student,s_aadhar_no'],
                            'student_email' => ['required', 'email'],
                            'student_gender' => ['required'],
                            'student_religion' => ['required'],
                            'student_caste' => ['required'],
                            'student_citizenship' => ['required'],
                            'student_subdivision' => ['required'],
                            's_pwd' => ['required'],
                            // 'student_photo' => ['required'],
                            // 'student_sign' => ['required'],
                            'student_home_dist' => ['required'],
                            'student_state_id' => ['required'],
                            'student_address' => ['required'],
                            'student_pin_no' => ['required'],
                            'is_married' => ['required'],
                            'exam_total_marks' => ['required', 'numeric', 'min:1'],
                            'exam_board' => ['required'],
                            'exam_pass_yr' => ['required'],
                            'exam_result' => ['required'],
                            'obtained_marks' => ['required', 'numeric', 'min:0'],
                            'trade_code' => ['required'],
                            's_form_num' => 'required'
                        ]);

                        if ($validator->fails()) {
                            return response()->json([
                                'error' => true,
                                'message' => $validator->messages()
                            ], 422);
                        }

                        try {
                            $now = now();
                            $form_num = $request->s_form_num;
                            $student = Student::where('s_appl_form_num', $form_num)->where('is_active', 1)->first();

                            if (!$student) {
                                return response()->json([
                                    'error' => true,
                                    'message' => 'Student not found with this application number.'
                                ], 404);
                            }

                            // Age validation
                            $currentYear = date('Y');
                            $cutoffYear = $currentYear - 17;
                            $cutoffDate = strtotime("$cutoffYear-07-01");
                            $studentDob = strtotime($request->student_dob);
                            $examTotalMarks = (int) $request->exam_total_marks;
                            $obtainedMarks = (int) $request->obtained_marks;
                            if ($obtainedMarks > $examTotalMarks) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Obtained marks cannot be greater than total marks.'
                                ], 400);
                            }


                            if ($studentDob > $cutoffDate) {
                                return response()->json([
                                    'error' => true,
                                    'message' => "Candidate must complete 17 years on or before July 1st, $cutoffYear."
                                ], 400);
                            }
                            $student_photo = null;
                            if ($request->hasFile('student_photo')) {
                                $image = $request->file('student_photo');
                                $imageName = $form_num . '_image.' . $image->getClientOriginalExtension();
                                $imagePath = 'uploads/' . $imageName;
                                $image->storeAs('uploads/', $imageName, 'public');
                                $student_photo = $imagePath;
                            }

                            // Save signature
                            $student_sign = null;
                            if ($request->hasFile('student_sign')) {
                                $signature = $request->file('student_sign');
                                $signatureName = $form_num . '_sign.' . $signature->getClientOriginalExtension();
                                $signaturePath = 'uploads/' . $signatureName;
                                $signature->storeAs('uploads/', $signatureName, 'public');
                                $student_sign = $signaturePath;
                            }
                            $instituteCode = Institute::where('i_id', $request->student_inst_id)->value('i_code');


                            // Prepare formatted names
                            $firstName = Str::upper($request->student_first_name);
                            $middleName = (!empty($request->student_middle_name) && strtolower(trim($request->student_middle_name)) !== 'null')
                                ? Str::upper(trim($request->student_middle_name))
                                : null;
                            $lastName = Str::upper($request->student_last_name);
                            $fullName = trim("$firstName $middleName $lastName");
                            $enc_aadhaar_num = encryptHEXFormat($request->student_aadhar_no);

                            DB::beginTransaction();

                            $student->update([
                                's_inst_code'         => $instituteCode,
                                's_first_name'        => trim($request->student_first_name),
                                's_middle_name'       => $middleName,
                                's_last_name'         => trim($request->student_last_name),
                                's_candidate_name'    => trim($request->student_first_name)
                                    . (
                                        (isset($request->student_middle_name) &&
                                            !empty(trim($request->student_middle_name)) &&
                                            strtolower(trim($request->student_middle_name)) !== 'null')
                                        ? ' ' . trim($request->student_middle_name)
                                        : ''
                                    )
                                    . ' ' . trim($request->student_last_name),
                                's_father_name'       => trim($request->student_father_name),
                                's_mother_name'       => trim($request->student_mother_name),
                                's_dob'               => $request->student_dob,
                                's_aadhar_no'         => $enc_aadhaar_num,
                                's_email'             => trim($request->student_email),
                                's_gender'            => $request->student_gender,
                                's_religion'          => $request->student_religion,
                                's_caste'             => $request->student_caste,
                                's_subdivision'       => $request->student_subdivision,
                                's_address2'          => $request->student_address2,
                                's_pwd'               => $request->s_pwd,
                                's_photo'             => $student_photo,
                                's_sign'              => $student_sign,
                                's_guardian_name'     => $request->student_guardian_name,
                                's_citizenship'       => $request->student_citizenship,
                                's_pin_no'            => $request->student_pin_no,
                                's_home_district'     => trim($request->student_home_dist),
                                's_state_id'          => $request->student_state_id,
                                's_address'           => trim($request->student_address),
                                's_trade_code'        => $request->trade_code,
                                'is_married'          => $request->is_married,
                                's_kanyashree'        => $request->student_kanyashree_no,

                            ]);


                            JexpoApplElgbExam::where('exam_appl_form_num', $form_num)
                                ->update([
                                    'exam_board'     => $request->exam_board,
                                    'exam_pass_yr'   => $request->exam_pass_yr,
                                    'exam_tot_marks' => $examTotalMarks,
                                    'exam_ob_marks'  => $obtainedMarks,
                                    'exam_result'    => $request->exam_result,
                                    'updated_at'     => $now,
                                ]);


                            auditTrail(
                                $form_num,
                                "{$fullName} has successfully updated profile at {$student->s_phone} on {$now}.",
                                'update'
                            );

                            DB::commit();

                            return response()->json([
                                'error' => false,
                                'message' => 'Data updated successfully'
                            ], 200);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'error' => true,
                                'message' => $e->getMessage()
                            ], 500);
                        }
                    } else {
                        return response()->json([
                            'error'     =>  true,
                            'message'   =>  "Oops! you don't have sufficient permission"
                        ], 403);
                    }
                } else {
                    return response()->json([
                        'error'     =>  true,
                        'message'   =>  "Oops! you don't have sufficient permission"
                    ], 401);
                }
            } else {
                return response()->json([
                    'error'     =>  true,
                    'message'   =>  'Unable to process your request due to invalid token'
                ], 401);
            }
        } else {
            return response()->json([
                'error'     =>  true,
                'message'   =>  'Unable to process your request due to non availability of token'
            ], 401);
        }
    }


    public function downloadAdmissionFees(Request $request, $form_num)
    {

        $students = Student::select('s_appl_form_num', 's_candidate_name', 's_caste', 's_guardian_name', 's_mother_name', 's_dob', 's_phone', 's_inst_code', 's_trade_code')->where(['s_appl_form_num' => $form_num])->first();
        $institute = Institute::select('i_name')->where('i_code', $students->s_inst_code)->first();
        $trade = Trade::select('t_name')->where('t_code', $students->s_trade_code)->first();

        $payment = PaymentTransaction::where([
            'pmnt_modified_by' => $form_num,
            'pmnt_pay_type' => 'APPLICATION'
        ])->first();
        // dd($students);
        $pdf = Pdf::loadView('exports.application-fees', [
            'students' => $students,
            'institute_name' => $institute->i_name,
            'institute_code' => $institute->i_code,
            'trade_name' => $trade->t_name,
            'trade_code' => $trade->t_code,
            'payment' => $payment

        ]);

        return $pdf->setPaper('a4', 'landscape')
            ->setOption(['defaultFont' => 'sans-serif'])
            ->stream('application-fees.pdf');
    }
}
