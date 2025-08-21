<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\SuperUser;
use App\Models\Token;
use App\Http\Resources\StudentResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use App\Models\Institute;
use App\Models\Trade;
use App\Models\PaymentTransaction;
use App\Models\JexpoApplElgbExam;
use App\Models\Role;
use App\Models\State;
use App\Models\Subdivision;
use App\Models\District;
use App\Models\AuthPermission;
use App\Models\AuthUrl;
use Illuminate\Support\Facades\Artisan;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Crypt;



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
        $student = Student::where('s_appl_form_num', $form_num)->with('state:state_id_pk,state_name', 'district:district_id_pk,district_name', 'subdivision:id,name')->first();
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
        // dd("hi");
        $now    =   date('Y-m-d H:i:s');
        $today  =   date('Y-m-d');
        $time   =   date('H:i:s');

        try {
            $random = env('ENC_KEY');
            $check_student = Student::with([
                'state:state_id_pk,state_name',
                'district:district_id_pk,district_name'
            ])->where('s_appl_form_num', $form_num)->first();

            if ($check_student && is_numeric($check_student->s_subdivision)) {
                $check_student->load('subdivision:id,name');
            }
            if ($check_student && is_numeric($check_student->s_block)) {
                // Step 2: Reload with block relation
                $check_student->load('block:id,name');
            }
            $qualification = JexpoApplElgbExam::with(['state', 'district', 'board'])
                ->where('exam_appl_form_num', $form_num)
                ->first();
            $profile_save = (bool)$check_student->is_personal_save;
            $status = $msg = $message = "";
            if (!$check_student) {
                return response()->json([
                    'error'     =>  true,
                    'message'   => 'NO record Found'
                ],  200);
            }
            $student_photo_url = $check_student->s_photo
                ? URL::to("storage/{$check_student->s_photo}")
                : null;

            $student_sign_url = $check_student->s_sign
                ? URL::to("storage/{$check_student->s_sign}")
                : null;
            $student_llq_url = $check_student->s_llq_doc
                ? URL::to("storage/{$check_student->s_llq_doc}")
                : null;

            $student_tfw_url = $check_student->s_tfw_doc
                ? URL::to("storage/{$check_student->s_tfw_doc}")
                : null;
            $student_ews_url = $check_student->s_ews_doc
                ? URL::to("storage/{$check_student->s_ews_doc}")
                : null;
            $student_pwd_url = $check_student->s_pwd_doc
                ? URL::to("storage/{$check_student->s_pwd_doc}")
                : null;
            $student_exsm_url = $check_student->s_exsm_doc
                ? URL::to("storage/{$check_student->s_exsm_doc}")
                : null;
            $student_caste_url = $check_student->s_caste_doc
                ? URL::to("storage/{$check_student->s_caste_doc}")
                : null;
            $student_age_url = $check_student->s_age_doc
                ? URL::to("storage/{$check_student->s_age_doc}")
                : null;
            $student_marksheet_url = $check_student->s_marksheet_doc
                ? URL::to("storage/{$check_student->s_marksheet_doc}")
                : null;

            $student_adhar_url = $check_student->s_adhar_doc
                ? URL::to("storage/{$check_student->s_adhar_doc}")
                : null;
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

                'student_district' => [
                    'district_id'   => $check_student->s_home_district ?? '',
                    'district_name' => $check_student->district->district_name ?? '',
                ],
                'student_block' => [
                    'block_id'   => is_numeric($check_student->s_block)
                        ? $check_student->s_block
                        : null,

                    'block_name' => is_numeric($check_student->s_block)
                        ? ($check_student->block->name ?? '')
                        : $check_student->s_block,
                ],

                'student_subdivision' => [
                    'subdivision_id'   => is_numeric($check_student->s_subdivision)
                        ? $check_student->s_subdivision
                        : null,

                    'subdivision_name' => is_numeric($check_student->s_subdivision)
                        ? ($check_student->subdivision->name ?? '')
                        : $check_student->s_subdivision,
                ],

                'student_email' => $check_student->s_email,
                'student_gender' => $check_student->s_gender,
                'student_religion' => $check_student->s_religion,
                'student_caste' => $check_student->s_caste,
                'student_tfw' => $check_student->s_tfw,
                'student_llq' => $check_student->s_llq,
                'student_ews' => $check_student->s_ews,
                'student_exsm' => $check_student->s_exsm,
                // 'student_block' => $check_student->s_block,
                'student_post_office' => $check_student->s_post_office,
                'student_police_station' => $check_student->s_police_station,
                'student_adharno' => decryptHEXFormat($check_student->s_aadhar_no),
                'student_address' => $check_student->s_address,
                'student_aadhar_document' =>     $student_adhar_url,
                'student_address2' => $check_student->s_address2,
                'student_pin_no' => $check_student->s_pin_no,
                'student_phone' => $check_student->s_phone,
                'student_guardian_name' => $check_student->s_guardian_name,
                'student_is_married' => $check_student->is_married,
                'student_pwd' => $check_student->s_pwd,
                'student_photo' => $student_photo_url,
                'student_sign' => $student_sign_url,
                'student_citizenship' => $check_student->s_citizenship,
                'student_kanyashree' => $check_student->s_kanyashree,
                'student_caste_doc' => $student_caste_url,
                'student_pwd_doc'   => $student_pwd_url,
                'student_tfw_doc'   => $student_tfw_url,
                'student_llq_doc'   => $student_llq_url,
                'student_exsm_doc'  => $student_exsm_url,
                'student_ews_doc'   => $student_ews_url,
                'student_age_doc'   => $student_age_url,
                'student_marksheet_doc'   => $student_marksheet_url,
                'session_year' => $check_student->session_year,
                'is_paid' => (bool)$check_student->is_payment,
                'is_applied' => (bool)$check_student->is_personal_save,
                'is_approved' => (bool)$check_student->is_approved,
                'is_reject' => (bool)$check_student->is_reject,
                'rejected_remarks' => $check_student->s_remarks ?? null,
                'student_cast_cert_number' =>  $check_student->cast_cert_number,
                'student_ews_cert_date' => $check_student->ews_cert_date,
                'student_pwd_cert_number' =>  $check_student->pc_cert_no,
                'student_pwd_cert_date' =>  $check_student->pc_cert_date,
                'student_ews_cert_number' =>  $check_student->ews_cert_number,
                'student_cast_cert_date' => $check_student->cast_cert_date,
                'student_ews_valid_year' => $check_student->ews_valid_year,
                'student_cast_sub_category' => $check_student->cast_sub_category ?? '',
                'student_bank_details' => $check_student->s_bank_details ? json_decode($check_student->s_bank_details, true) : null,

                'exam_board'       => [
                    'exam_state_code' =>  $qualification->board->state_code ?? '',
                    'exam_board_code' =>  $qualification->exam_board ?? '',
                    'exam_board_name' =>  $qualification->board->board_name ?? '',

                ],

                'exam_state'       => [
                    'exam_state_code' =>    $qualification->exam_state_code ?? '',
                    'exam_state_name' =>    $qualification->state->state_name ?? '',
                ],
                'exam_district'       => [
                    'exam_district_code' =>    $qualification->exam_district ?? '',
                    'exam_district_name' =>    $qualification->district->district_name ?? '',
                ],
                // 'exam_district'      => $qualification->district->district_name ?? '',
                'exam_school_name' => $qualification->exam_school_name,
                'exam_pass_yr'     => $qualification->exam_pass_yr,
                'exam_tot_marks'   => $qualification->exam_tot_marks,
                'exam_ob_marks'    => $qualification->exam_ob_marks,
                'exam_elgb_code'   => $qualification->exam_elgb_code,
                'exam_marks_type'  => $qualification->exam_marks_type,
                'exam_per_marks'   => $qualification->exam_per_marks ? json_decode($qualification->exam_per_marks, true) : null,


            ];
            return response()->json([
                'error'     =>  false,
                'message'   =>  $message,
                'is_profile_updated' =>  $profile_save,
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
                $user_data = Student::where('s_id', $user_id)->first();
                if ($user_data) {
                    $user_role_id = $user_data->u_role_id;
                } else {
                    $admin_user = SuperUser::where('u_id', $user_id)->first();
                    $user_role_id = $admin_user->u_role_id;
                }
                $role_url_access_id = AuthPermission::where('rp_role_id', $user_role_id)->pluck('rp_url_id');
                if (sizeof($role_url_access_id) > 0) {
                    $urls = AuthUrl::where('url_visible', 1)->whereIn('url_id', $role_url_access_id)->get()->toArray();
                    $url_data = array_column($urls, 'url_name');
                    if (in_array('student-update', $url_data)) {
                        $validator = Validator::make($request->all(), [
                            'student_first_name'   => ['required'],
                            'student_last_name'    => ['required'],
                            'student_father_name'  => ['required'],
                            'student_mother_name'  => ['required'],
                            'student_guardian_name' => ['required'],
                            'student_dob'          => ['required'],
                            'student_aadhar_no'    => ['required', 'unique:jexpo_register_student,s_aadhar_no'],
                            'student_email'        => ['required', 'email'],
                            'student_gender'       => ['required'],
                            'student_religion'     => ['required'],
                            'student_caste'        => ['required'],
                            'student_citizenship'  => ['required'],
                            'student_subdivision'  => ['nullable'],
                            'is_pwd'                => ['required'],
                            'student_photo'        => ['required'],
                            'student_sign'         => ['required'],
                            'student_home_dist'    => ['required'],
                            'student_state_id'     => ['required'],
                            'student_address'      => ['required'],
                            'student_pin_no'       => ['required'],
                            'is_married'           => ['required'],
                            'student_phone' => 'required',
                            'is_tfw'           => ['required'],
                            'is_llq'           => ['required'],
                            'is_exsm'           => ['required'],
                            'is_ews'           => ['required'],
                            'student_aadhar_document' => ['required'],
                            'student_block' => ['nullable'],
                            'exam_board' => 'required',
                            'exam_pass_yr' => 'required',
                            'exam_total_marks' => 'required',
                            'obtained_marks' => 'required',
                            'exam_elgb_code' => 'required',
                            'exam_school_name' => 'required',
                            'exam_marks' => 'required',
                            'exam_state' => 'required',
                            'exam_district' => 'required',
                            'student_appl_form_num' => 'required',
                            'student_police_station' => 'required',
                            'student_post_office' => 'required',
                            'student_age_proof_document' => ['required'],
                            'student_marksheet_document' => ['required'],
                            // 'bank_details' => 'required',
                        ]);

                        if ($validator->fails()) {
                            return response()->json([
                                'error' => true,
                                'message' => $validator->messages()
                            ], 422);
                        }

                        try {
                            $now = now();
                            $form_num = $request->student_appl_form_num;
                            $student = Student::where('s_appl_form_num', $form_num)->where('is_active', 1)->first();
                            $profile_save = (bool)$student->is_personal_save;

                            if (!$student) {
                                return response()->json([
                                    'error' => true,
                                    'message' => 'Student not found with this application number.'
                                ], 404);
                            }

                            // Age validation
                            $currentYear = date('Y');
                            $currentTime = time();
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



                            $student_photo = $student->s_photo; // default to existing

                            if ($request->hasFile('student_photo') && $request->file('student_photo')->isValid()) {
                                // Unlink old photo if exists
                                if (!empty($student->s_photo)) {
                                    $oldPhotoPath = storage_path('app/public/' . $student->s_photo);
                                    if (file_exists($oldPhotoPath)) {
                                        unlink($oldPhotoPath);
                                    }
                                }

                                $image = $request->file('student_photo');
                                $imageName = $form_num . '_image_' . $currentTime . '.' . $image->getClientOriginalExtension();
                                $imagePath = 'uploads/' . $imageName;
                                $image->storeAs('uploads/', $imageName, 'public');

                                $student_photo = $imagePath;
                            }

                            // SIGNATURE
                            $student_sign = $student->s_sign; // default to existing

                            if ($request->hasFile('student_sign') && $request->file('student_sign')->isValid()) {
                                // Unlink old signature if exists
                                if (!empty($student->s_sign)) {
                                    $oldSignPath = storage_path('app/public/' . $student->s_sign);
                                    if (file_exists($oldSignPath)) {
                                        unlink($oldSignPath);
                                    }
                                }

                                $signature = $request->file('student_sign');
                                $signatureName = $form_num . '_sign_' . $currentTime . '.' . $signature->getClientOriginalExtension();
                                $signaturePath = 'uploads/' . $signatureName;
                                $signature->storeAs('uploads/', $signatureName, 'public');

                                $student_sign = $signaturePath;
                            }


                            $student_tfw_document_path = null;
                            if ($request->is_tfw === '1') {
                                if ($request->hasFile('student_tfw_document') && $request->file('student_tfw_document')->isValid()) {
                                    $document = $request->file('student_tfw_document');
                                    $documentName = $form_num . '_tfw_document.' . $document->getClientOriginalExtension();
                                    $document->storeAs('uploads/', $documentName, 'public');
                                    $student_tfw_document_path = 'uploads/' . $documentName;
                                } elseif (is_string($request->student_tfw_document)) {
                                    $student_tfw_document_path =  $student->s_tfw_doc  ? $student->s_tfw_doc
                                        : null;
                                } else {
                                    return response()->json([
                                        'success' => false,
                                        'message' => 'TFW document is mandatory when TFW is selected.'
                                    ], 400);
                                }
                            }
                            if ($request->is_pwd === '1') {
                                if ($request->hasFile('student_pwd_document') && $request->file('student_pwd_document')->isValid()) {
                                    $document = $request->file('student_pwd_document');
                                    $documentName = $form_num . '_pwd_document.' . $document->getClientOriginalExtension();
                                    $document->storeAs('uploads/', $documentName, 'public');
                                    $student_pwd_document_path = 'uploads/' . $documentName;
                                } elseif (is_string($request->student_pwd_document)) {
                                    $student_pwd_document_path = $student->s_pwd_doc ?? null;
                                }
                                $pwd_certificate_number = trim($request->pc_cert_no ?? '') !== ''
                                    ? $request->pc_cert_no
                                    : ($student->pc_cert_no ?? null);

                                $pwd_certificate_issue_date = trim($request->pc_cert_date ?? '') !== ''
                                    ? $request->pc_cert_date
                                    : ($student->pc_cert_date ?? null);
                                if (empty($student_pwd_document_path) || empty($pwd_certificate_number) || empty($pwd_certificate_issue_date)) {
                                    return response()->json([
                                        'success' => false,
                                        'message' => 'PWD certificate number, issue date, and document are required when PWD is selected.'
                                    ], 400);
                                }
                            } else {
                                $student_pwd_document_path = null;
                                $pwd_certificate_number = '';
                                $pwd_certificate_issue_date = '';
                            }
                            $student_llq_document_path = null;
                            if ($request->is_llq === '1') {
                                if ($request->hasFile('student_llq_document') && $request->file('student_llq_document')->isValid()) {
                                    $document = $request->file('student_llq_document');
                                    $documentName = $form_num . '_llq_document.' . $document->getClientOriginalExtension();
                                    $document->storeAs('uploads/', $documentName, 'public');
                                    $student_llq_document_path = 'uploads/' . $documentName;
                                } elseif (is_string($request->student_llq_document)) {
                                    $student_llq_document_path =  $student->s_llq_doc  ? $student->s_llq_doc : null;
                                } else {
                                    return response()->json([
                                        'success' => false,
                                        'message' => 'LLQ document is mandatory when LLQ is selected.'
                                    ], 400);
                                }
                            }
                            $student_exsm_document_path = null;
                            if ($request->is_exsm === '1') {
                                if ($request->hasFile('student_exsm_document') && $request->file('student_exsm_document')->isValid()) {
                                    $document = $request->file('student_exsm_document');
                                    $documentName = $form_num . '_exsm_document.' . $document->getClientOriginalExtension();
                                    $document->storeAs('uploads/', $documentName, 'public');
                                    $student_exsm_document_path = 'uploads/' . $documentName;
                                } elseif (is_string($request->student_exsm_document)) {
                                    $student_exsm_document_path =  $student->s_exsm_doc  ? $student->s_exsm_doc : null;
                                } else {
                                    return response()->json([
                                        'success' => false,
                                        'message' => 'EXSM document is mandatory when EXSM is selected.'
                                    ], 400);
                                }
                            }
                            $student_caste_document_path = $student->s_caste_doc;
                            $certificate_number = null;
                            $certificate_issue_date = null;
                            $sub_caste_category = null;
                            if (strtoupper(trim($request->student_caste)) !== 'GENERAL') {
                                if ($request->hasFile('student_caste_document') && $request->file('student_caste_document')->isValid()) {
                                    $document = $request->file('student_caste_document');
                                    $documentName = $form_num . '_caste_document.' . $document->getClientOriginalExtension();
                                    $document->storeAs('uploads/', $documentName, 'public');
                                    $student_caste_document_path = 'uploads/' . $documentName;
                                } elseif (is_string($request->student_caste_document)) {
                                    $student_caste_document_path =  $student->s_caste_doc  ? $student->s_caste_doc
                                        : null;
                                } else {
                                    return response()->json([
                                        'success' => false,
                                        'message' => 'Caste certificate is mandatory when caste is not GENERAL.'
                                    ], 400);
                                }
                                $certificate_number = $request->cert_number;
                                $sub_caste_category = $request->sub_caste_category;
                                $certificate_issue_date = $request->cert_issue_date;
                            }

                            if ($request->is_ews === '1') {
                                if ($request->hasFile('student_ews_document') && $request->file('student_ews_document')->isValid()) {
                                    $document = $request->file('student_ews_document');
                                    $documentName = $form_num . '_ews_document.' . $document->getClientOriginalExtension();
                                    $document->storeAs('uploads/', $documentName, 'public');
                                    $student_ews_document_path = 'uploads/' . $documentName;
                                } elseif (is_string($request->student_ews_document)) {
                                    $student_ews_document_path = $student->s_ews_doc ?? null;
                                }
                                $ews_certificate_number = trim($request->ews_cert_number ?? '') !== ''
                                    ? $request->ews_cert_number
                                    : ($student->ews_cert_number ?? null);

                                $ews_certificate_issue_date = trim($request->ews_cert_date ?? '') !== ''
                                    ? $request->ews_cert_date
                                    : ($student->ews_cert_date ?? null);
                                $ews_valid_year = trim($request->ews_valid_year ?? '') !== ''
                                    ? $request->ews_valid_year
                                    : ($student->ews_valid_year ?? null);
                                if (empty($student_ews_document_path) || empty($ews_certificate_number) || empty($ews_certificate_issue_date) || empty($ews_valid_year)) {
                                    return response()->json([
                                        'success' => false,
                                        'message' => 'EWS certificate number, valid year, issue date, and document are required when EWS is selected.'
                                    ], 400);
                                }
                            } else {
                                // EWS is not selected, so clear values
                                $student_ews_document_path = null;
                                $ews_certificate_number = '';
                                $ews_certificate_issue_date = '';
                                $ews_valid_year = '';
                            }


                            if ($request->hasFile('student_aadhar_document') && $request->file('student_aadhar_document')->isValid()) {
                                $document = $request->file('student_aadhar_document');
                                $documentName = $form_num . '_aadhar_document.' . $document->getClientOriginalExtension();
                                $document->storeAs('uploads/', $documentName, 'public');
                                $student_aadhar_document_path = 'uploads/' . $documentName;
                            } elseif (is_string($request->student_aadhar_document)) {
                                $student_aadhar_document_path =  $student->s_adhar_doc  ? $student->s_adhar_doc : null;
                            } else {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'adhar document is mandatory '
                                ], 400);
                            }
                            if ($request->hasFile('student_age_proof_document')) {
                                $document = $request->file('student_age_proof_document');
                                $documentName = $form_num . '_age_proof_document.' . $document->getClientOriginalExtension();
                                $document->storeAs('uploads/', $documentName, 'public');
                                $student_age_proof_document_path = 'uploads/' . $documentName;
                            } elseif (is_string($request->student_age_proof_document)) {
                                $student_age_proof_document_path = $student->s_age_doc  ?  $student->s_age_doc
                                    : null;
                            } else {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'this document is mandatory'
                                ], 400);
                            }
                            if ($request->hasFile('student_marksheet_document')) {
                                $document = $request->file('student_marksheet_document');
                                $documentName = $form_num . '_marksheet_document.' . $document->getClientOriginalExtension();
                                $document->storeAs('uploads/', $documentName, 'public');
                                $student_marksheet_document_path = 'uploads/' . $documentName;
                            } elseif (is_string($request->student_marksheet_document)) {
                                $student_marksheet_document_path = $student->s_marksheet_doc  ?  $student->s_marksheet_doc
                                    : null;
                            } else {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'this document is mandatory'
                                ], 400);
                            }
                            //
                            $enc_aadhaar_num = encryptHEXFormat($request->student_aadhar_no);

                            DB::beginTransaction();
                            $bank_details = json_decode($request->bank_details, true);
                            // dd($certificate_number);
                            $student->update([
                                's_first_name'        => trim($request->student_first_name),
                                's_middle_name'       => trim($request->student_middle_name),
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
                                's_subdivision' => (
                                    isset($request->student_subdivision) &&
                                    trim(strtolower($request->student_subdivision)) !== '' &&
                                    trim(strtolower($request->student_subdivision)) !== 'null'
                                ) ? $request->student_subdivision : null,
                                's_address2'          => $request->student_address2,
                                's_pwd'               => $request->is_pwd,
                                's_photo'             => $student_photo,
                                's_sign'              => $student_sign,
                                's_guardian_name'     => $request->student_guardian_name,
                                's_citizenship'       => $request->student_citizenship,
                                's_pin_no'            => $request->student_pin_no,
                                's_police_station'         => $request->student_police_station,
                                's_post_office'         => $request->student_post_office,
                                's_home_district'     => trim($request->student_home_dist),
                                's_state_id'          => $request->student_state_id,
                                's_address'           => trim($request->student_address),
                                's_trade_code'        => $request->trade_code,
                                'is_married'          => $request->is_married,
                                's_kanyashree'        => $request->student_kanyashree_no,
                                's_tfw'     => $request->is_tfw,
                                's_ews'     => $request->is_ews,
                                's_llq'     => $request->is_llq,
                                's_exsm'    => $request->is_exsm,
                                's_caste_doc' => $student_caste_document_path,
                                's_pwd_doc'   => $student_pwd_document_path,
                                's_tfw_doc'   => $student_tfw_document_path,
                                's_llq_doc'   => $student_llq_document_path,
                                's_exsm_doc'  => $student_exsm_document_path,
                                's_ews_doc'   => $student_ews_document_path,
                                's_age_doc'   => $student_age_proof_document_path,
                                's_marksheet_doc' => $student_marksheet_document_path,
                                'cast_cert_number' =>  $certificate_number,
                                'cast_cert_date' =>  $certificate_issue_date,
                                'cast_sub_category' => $sub_caste_category,
                                's_block' => $request->student_block,
                                's_adhar_doc' => $student_aadhar_document_path,
                                's_bank_details' => json_encode($bank_details),
                                'ews_cert_number' => $ews_certificate_number,
                                'ews_cert_date' => $ews_certificate_issue_date,
                                'ews_valid_year' => $ews_valid_year,
                                'pc_cert_no' => $pwd_certificate_number,
                                'pc_cert_date' => $pwd_certificate_issue_date,



                            ]);


                            $exam_per_marks = json_decode($request->exam_marks, true); // this is the nested subject-wise marks

                            JexpoApplElgbExam::updateOrCreate(
                                [
                                    'exam_appl_form_num' => $form_num,
                                ],
                                [
                                    'exam_elgb_code'    => $request->exam_elgb_code,
                                    'exam_board'         => $request->exam_board,
                                    'exam_pass_yr'       => $request->exam_pass_yr,
                                    'exam_tot_marks'     => $request->exam_total_marks,
                                    'exam_ob_marks'      => $request->obtained_marks,
                                    'exam_school_name'   => $request->exam_school_name,
                                    'exam_marks_type'    => $request->exam_marks_type ?? null,
                                    'exam_per_marks'     => json_encode($exam_per_marks),
                                    'exam_state_code'         => $request->exam_state,
                                    'exam_district'      => $request->exam_district,
                                    'updated_at'         => now(),
                                ]
                            );

                            auditTrail(
                                $form_num,
                                "{$student->s_candidate_name} has successfully updated profile at {$student->s_phone} on {$now}.",
                                'update'
                            );

                            DB::commit();

                            return response()->json([
                                'error' => false,
                                'message' => 'Data updated successfully',
                                'is_profile_updated' => $profile_save
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

        $students = Student::with('state', 'district', 'subdivision', 'block')->where(['s_appl_form_num' => $form_num])->first();
        $education = JexpoApplElgbExam::with('board', 'district')->where('exam_appl_form_num', $form_num)->first();
        $bank_details = $students->s_bank_details ? json_decode($students->s_bank_details, true) : null;
        $subjects  =  $education->exam_per_marks ? json_decode($education->exam_per_marks, true) : null;
        try {
            $aadhaar = decryptHEXFormat($students->s_aadhar_no);

            // Keep only last 4 digits visible
            $students->s_aadhar_no = str_repeat('X', strlen($aadhaar) - 4) . substr($aadhaar, -4);
        } catch (\Exception $e) {
            // fallback if decrypt fails
            $students->s_aadhar_no = '[Invalid Aadhaar]';
        }
        $payment = PaymentTransaction::where([
            'pmnt_modified_by' => $form_num,
            'pmnt_pay_type' => 'APPLICATION'
        ])->first();

        $qrContent = [
            $students->s_appl_form_num,
            $students->s_candidate_name,
            $students->s_dob,
            $students->s_phone,
            $students->s_email,
        ];

        $qr_text = implode(',', $qrContent);

        // dd($qr_text);

        $qrcode = QrCode::format('svg')
            ->size(60)
            ->backgroundColor(255, 255, 255)
            ->color(0, 0, 0)
            ->margin(1)
            ->generate(
                $qr_text,
            );
        $pdf = Pdf::loadView('exports.application-fees', [
            'students' => $students,
            'education' => $education,
            'subjects' => $subjects,
            'bank_details' => $bank_details,
            'payment' => $payment,
            'qr_code' => base64_encode($qrcode),

        ]);
        $pdf->setOption(['defaultFont' => 'sans-serif'])
            ->setPaper('a4', 'portrait')
            ->output(); // render first

        $pdf->getDomPDF()->getCanvas()->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $text = "Page $pageNumber of $pageCount";
            $font = $fontMetrics->get_font("Arial", "normal");
            $size = 9;
            $width = $fontMetrics->get_text_width($text, $font, $size);

            // Position → bottom-right
            $x = $canvas->get_width() - $width - 40;
            $y = $canvas->get_height() - 20;

            $canvas->text($x, $y, $text, $font, $size);
        });
        return $pdf->stream('poly1styr-' . $form_num . '.pdf');
    }

    public function downloadAdmissionFeesExcel(Request $request)
    {
        $query = Student::with([
            'state:state_id_pk,state_name',
            'district:district_id_pk,district_name'
        ])->orderBy('s_appl_form_num', 'asc');


        if ($request->type == 'payment') {
            $query->where('is_payment', 1);
        }

        if ($request->type == 'application') {
            $query->where('is_personal_save', 1);
        }
        $studentsData = $query->get()->map(function ($student) {
            if (is_numeric($student->s_subdivision)) {
                $student->loadMissing('subdivision:id,name');
            }
            if (is_numeric($student->s_block)) {
                $student->loadMissing('block:id,name');
            }

            $education = JexpoApplElgbExam::with('board')
                ->where('exam_appl_form_num', $student->s_appl_form_num)
                ->first();

            $bank_details = $student->s_bank_details ? json_decode($student->s_bank_details, true) : [];
            $subjects     = $education && $education->exam_per_marks ? json_decode($education->exam_per_marks, true) : [];

            $payment = PaymentTransaction::where([
                'pmnt_modified_by' => $student->s_appl_form_num,
                'pmnt_pay_type'    => 'APPLICATION'
            ])->first();
            $subjectMarks = [];
            foreach ($subjects as $subj) {
                $key = str_replace(' ', '_', strtolower($subj['subject']));
                $subjectMarks[$key . '_total_marks'] = $subj['total'] ?? '';
                $subjectMarks[$key . '_obtained_marks'] = $subj['obtained'] ?? '';
            }

            return array_merge([
                'Application Number' => $student->s_appl_form_num,
                'Candidate Name'     => $student->s_candidate_name,
                'Father Name'        => $student->s_father_name,
                'Mother Name'        => $student->s_mother_name,
                'DOB'                => $student->s_dob,
                'Gender'             => $student->s_gender,
                'Email'              => $student->s_email,
                'Phone'              => $student->s_phone,
                'State'              => $student->state->state_name ?? '',
                'District'           => $student->district->district_name ?? '',
                'Subdivision'        => is_numeric($student->s_subdivision) ? optional($student->subdivision)->name : $student->s_subdivision,
                'Block'              => is_numeric($student->s_block) ? optional($student->block)->name : $student->s_block,
                'PIN'                => $student->s_pin_no,
                'Religion'           => $student->s_religion,
                'Caste'              => $student->s_caste,
                'TFW'                => $student->s_tfw == 1 ? 'Yes' : 'No',
                'LLQ'                => $student->s_llq == 1 ? 'Yes' : 'No',
                'EWS'                => $student->s_ews == 1 ? 'Yes' : 'No',
                'EXSM'               => $student->s_exsm == 1 ? 'Yes' : 'No',
                'Post_Office'        => $student->s_post_office,
                'Police_Station'     => $student->s_police_station,
                'Aadharno'           => decryptHEXFormat($student->s_aadhar_no),
                'Married'            => $student->is_married == 1 ? 'Yes' : 'No',
                'Citizenship'        => $student->s_citizenship,
                'Caste_cert_no'      => $student->cast_cert_number,
                'Caste_cert_date'    => $student->cast_cert_date,
                'Cast_sub_category'  => $student->cast_sub_category,
                'EWS_cert_no'        => $student->ews_cert_number,
                'EWS_cert_date'      => $student->ews_cert_date,
                'Board'              => $education->board->board_name ?? '',
                'Exam Name'          => $education->exam_elgb_code ?? '',
                'Exam Pass Year'     => $education->exam_pass_yr ?? '',
                'Exam School Name'   => $education->exam_school_name ?? '',
                'Bank Name'          => $bank_details['bankName'] ?? '',
                'Account No'         => $bank_details['accNumber'] ?? '',
                'IFSC Code'          => $bank_details['IFSC'] ?? '',
                'Subjects'           => collect($subjects)->pluck('subject')->implode(', '),
                'Total Marks'        => $education->exam_tot_marks ?? '',
                'Obtained Marks'     => $education->exam_ob_marks ?? '',
                'Payment Amount'     => $payment->trans_amount ?? '',
                'Payment Date'       => $payment?->trans_time ? date('Y-m-d', strtotime($payment->trans_time)) : '',
                'Payment Status'     => $payment->trans_status ?? '',
            ], $subjectMarks);
        });

        return response()->json([
            'error' => false,
            'data'  => $studentsData,
            'count' => $studentsData->count()
        ], 200);
    }
}
