<?php

namespace App\Http\Controllers;

use Exception;
use DateTime;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\Token;
use App\Models\Trade;
use Illuminate\Http\Request;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use App\Models\District;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Institute;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\JexpoApplElgbExam;
use Illuminate\Support\Facades\Validator;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\SuperUser;
use App\Models\AuditTrail;
use App\Models\AuthPermission;
use App\Models\AuthUrl;



class AdmissionController extends Controller
{
    public function submitStudents(Request $request)
    {
        try {
            $validated = Validator::make($request->all(), [
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
                'student_subdivision'  => ['required'],
                'is_pwd'                => ['required'],
                'student_photo'        => ['required', 'file'],
                'student_sign'         => ['required', 'file'],
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
                'student_age_proof_document' => ['required'],
                'student_marksheet_document' => ['required'],
                'student_block' => ['required'],
                // 'exam_qualifications' => 'required',
                'exam_board' => 'required',
                'exam_pass_yr' => 'required',
                'exam_total_marks' => 'required',
                'obtained_marks' => 'required',
                'exam_elgb_code' => 'required',
                'exam_school_name' => 'required',
                // 'exam_marks_type' => 'required',
                'exam_marks' => 'required',
                'exam_state' => 'required',
                'student_police_station' => 'required',
                'student_post_office' => 'required',
                'bank_details' => 'required',



            ]);
            if ($validated->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => $validated->errors()->first()
                ], 422);
            }
            $is_student_updated = true;
            $is_personal_details = false;

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

            // Schedule check
            $currentDateTime = now();
            $schedule = Schedule::where('sch_event', 'APPLICATION')
                ->where('sch_round', 1)
                ->where('sch_start_dt', '<=', $currentDateTime)
                ->where('sch_end_dt', '>=', $currentDateTime)
                ->exists();

            if (!$schedule) {
                return response()->json([
                    'error' => true,
                    'message' => "Application time expired."
                ], 400);
            }
            $student_phone = $request->student_phone;
            $student = Student::where('s_phone', $student_phone)->where('is_active', 1)->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Active student record not found.'
                ], 404);
            } else {
                $s_appl_form_num = $student->s_appl_form_num;
                $enc_aadhaar_num = encryptHEXFormat($request->student_aadhar_no);
                $now = now();
                $year = date('Y');
                $sessionYear = sessionYear($year);
                $student_photo = null;
                $certificate_number = null;
                $sub_caste_category = null;
                $certificate_issue_date = null;
                if ($request->hasFile('student_photo')) {
                    $image = $request->file('student_photo');
                    $imageName = $s_appl_form_num . '_image.' . $image->getClientOriginalExtension();
                    $image->storeAs('uploads/', $imageName, 'public');
                    $student_photo = 'uploads/' . $imageName;
                }

                $student_sign = null;
                if ($request->hasFile('student_sign')) {
                    $signature = $request->file('student_sign');
                    $signatureName = $s_appl_form_num . '_sign.' . $signature->getClientOriginalExtension();
                    $signature->storeAs('uploads/', $signatureName, 'public');
                    $student_sign = 'uploads/' . $signatureName;
                }
                $student_tfw_document_path = null;
                if ($request->is_tfw === '1') {
                    if ($request->hasFile('student_tfw_document')) {
                        $document = $request->file('student_tfw_document');
                        $documentName = $s_appl_form_num . '_tfw_document.' . $document->getClientOriginalExtension();
                        $document->storeAs('uploads/', $documentName, 'public');
                        $student_tfw_document_path = 'uploads/' . $documentName;
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'TFW document is mandatory when TFW is selected.'
                        ], 400);
                    }
                }
                $student_pwd_document_path = null;
                if ($request->is_pwd === '1') {
                    if ($request->hasFile('student_pwd_document')) {
                        $document = $request->file('student_pwd_document');
                        $documentName = $s_appl_form_num . '_pwd_document.' . $document->getClientOriginalExtension();
                        $document->storeAs('uploads/', $documentName, 'public');
                        $student_pwd_document_path = 'uploads/' . $documentName;
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'PWD document is mandatory when PWD is selected.'
                        ], 400);
                    }
                }
                $student_llq_document_path = null;
                if ($request->is_llq === '1') {
                    if ($request->hasFile('student_llq_document')) {
                        $document = $request->file('student_llq_document');
                        $documentName = $s_appl_form_num . '_llq_document.' . $document->getClientOriginalExtension();
                        $document->storeAs('uploads/', $documentName, 'public');
                        $student_llq_document_path = 'uploads/' . $documentName;
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'LLQ document is mandatory when LLQ is selected.'
                        ], 400);
                    }
                }
                $student_exsm_document_path = null;
                if ($request->is_exsm === '1') {
                    if ($request->hasFile('student_exsm_document')) {
                        $document = $request->file('student_exsm_document');
                        $documentName = $s_appl_form_num . '_exsm_document.' . $document->getClientOriginalExtension();
                        $document->storeAs('uploads/', $documentName, 'public');
                        $student_exsm_document_path = 'uploads/' . $documentName;
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'EXSM document is mandatory when EXSM is selected.'
                        ], 400);
                    }
                }
                $student_caste_document_path = null;
                if (strtoupper(trim($request->student_caste)) !== 'GENERAL') {
                    if ($request->hasFile('student_caste_document')) {
                        $document = $request->file('student_caste_document');
                        $documentName = $s_appl_form_num . '_caste_document.' . $document->getClientOriginalExtension();
                        $document->storeAs('uploads/', $documentName, 'public');
                        $student_caste_document_path = 'uploads/' . $documentName;
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
                $student_ews_document_path = null;
                if ($request->is_ews === '1') {
                    if ($request->hasFile('student_ews_document')) {
                        $document = $request->file('student_ews_document');
                        $documentName = $s_appl_form_num . '_ews_document.' . $document->getClientOriginalExtension();
                        $document->storeAs('uploads/', $documentName, 'public');
                        $student_ews_document_path = 'uploads/' . $documentName;
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'EWS document is mandatory when EWS is selected.'
                        ], 400);
                    }
                    $certificate_number = $request->cert_number;
                    $certificate_issue_date = $request->cert_issue_date;
                }
                if ($request->hasFile('student_aadhar_document')) {
                    $document = $request->file('student_aadhar_document');
                    $documentName = $s_appl_form_num . '_aadhar_document.' . $document->getClientOriginalExtension();
                    $document->storeAs('uploads/', $documentName, 'public');
                    $student_aadhar_document_path = 'uploads/' . $documentName;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'adhar document is mandatory when EXSM is selected.'
                    ], 400);
                }
                if ($request->hasFile('student_age_proof_document')) {
                    $document = $request->file('student_age_proof_document');
                    $documentName = $s_appl_form_num . '_age_proof_document.' . $document->getClientOriginalExtension();
                    $document->storeAs('uploads/', $documentName, 'public');
                    $student_age_proof_document_path = 'uploads/' . $documentName;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'this document is mandatory'
                    ], 400);
                }
                if ($request->hasFile('student_marksheet_document')) {
                    $document = $request->file('student_marksheet_document');
                    $documentName = $s_appl_form_num . '_marksheet_document.' . $document->getClientOriginalExtension();
                    $document->storeAs('uploads/', $documentName, 'public');
                    $student_marksheet_document_path = 'uploads/' . $documentName;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'this document is mandatory'
                    ], 400);
                }
                // dd($certificate_number, $certificate_issue_date);

                $bank_details = json_decode($request->bank_details, true);
                $middleName = (!empty($request->student_middle_name) && strtolower(trim($request->student_middle_name)) !== 'null')
                    ? Str::upper(trim($request->student_middle_name))
                    : null;
                $student->update([
                    's_first_name'     => trim($request->student_first_name),
                    's_middle_name'    => $middleName,
                    's_last_name'      => trim($request->student_last_name),
                    's_candidate_name' => trim($request->student_first_name)
                        . ($middleName ? ' ' . $middleName : '')
                        . ' ' . trim($request->student_last_name),
                    's_father_name'    => trim($request->student_father_name),
                    's_mother_name'    => trim($request->student_mother_name),
                    's_dob'            => $request->student_dob,
                    's_aadhar_no'      => $enc_aadhaar_num,
                    's_email'          => trim($request->student_email),
                    's_gender'         => $request->student_gender,
                    's_religion'       => $request->student_religion,
                    's_caste'          => $request->student_caste,
                    's_citizenship'    => $request->student_citizenship,
                    's_subdivision'    => $request->student_subdivision,
                    's_pwd'            => $request->is_pwd,
                    's_photo'          => $student_photo,
                    's_sign'           => $student_sign,
                    's_home_district'  => trim($request->student_home_dist),
                    's_state_id'       => $request->student_state_id,
                    's_address'        => trim($request->student_address),
                    's_address2'       => $request->student_address2,
                    'is_married'       => $request->is_married,
                    's_kanyashree'     => $request->student_kanyashree_no,
                    's_pin_no'         => $request->student_pin_no,
                    's_police_station'         => $request->student_police_station,
                    's_post_office'         => $request->student_post_office,
                    's_guardian_name'  => $request->student_guardian_name,
                    'is_active'        => 1,
                    'session_year'     => $sessionYear,
                    's_tfw'     => $request->is_tfw,
                    's_ews'     => $request->is_ews,
                    's_llq'     => $request->is_llq,
                    's_exsm'    => $request->is_exsm,
                    'is_personal_save' => true,
                    's_caste_doc' => $student_caste_document_path,
                    's_pwd_doc'   => $student_pwd_document_path,
                    's_tfw_doc'   => $student_tfw_document_path,
                    's_llq_doc'   => $student_llq_document_path,
                    's_exsm_doc'  => $student_exsm_document_path,
                    's_ews_doc'   => $student_ews_document_path,
                    's_age_doc'   => $student_age_proof_document_path,
                    's_marksheet_doc' => $student_marksheet_document_path,
                    'cast_cert_number' =>  $certificate_number,
                    'cast_cert_date' => $certificate_issue_date,
                    'cast_sub_category' => $sub_caste_category,
                    's_block' => $request->student_block,
                    's_adhar_doc' => $student_aadhar_document_path,
                    's_bank_details' => json_encode($bank_details)

                ]);
                $exam_per_mrks = json_decode($request->exam_marks, true);
                // dd($exam_per_mrks);
                JexpoApplElgbExam::create([
                    'exam_appl_form_num' => $s_appl_form_num,
                    'exam_board'         => $request->exam_board,
                    'exam_pass_yr'       => $request->exam_pass_yr,
                    'exam_tot_marks'     => $request->exam_total_marks,
                    'exam_ob_marks'      => $request->obtained_marks,
                    'exam_elgb_code'     => $request->exam_elgb_code,
                    'exam_marks_type'    => $request->exam_marks_type,
                    'exam_state_code'         => $request->exam_state,
                    'exam_school_name'   => $request->exam_school_name,
                    'exam_per_marks'     =>  json_encode($exam_per_mrks),
                ]);
                auditTrail(
                    $s_appl_form_num,
                    "{$request->student_first_name} {$request->student_last_name} has successfully inserted profile at {$student->s_phone} on {$now}.",
                    'insert'
                );




                return response()->json([
                    'success' => true,
                    'message' => 'student updated successfully',
                    'is_profile_updated' => $is_student_updated

                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' =>  $e->getMessage()
            ], 500);
        }
    }
    public function admissionList(Request $request)
    {
        if ($request->header('token')) {
            $now    =   date('Y-m-d H:i:s');
            $token_check = Token::where('t_token', '=', $request->header('token'))->where('t_expired_on', '>=', $now)->first();
            if ($token_check) {  // check the token is expire or not
                $user_id = $token_check->t_user_id;

                $user_data = SuperUser::select('u_id', 'u_ref', 'u_role_id')->where('u_id', $user_id)->first();
                $role_url_access_id = AuthPermission::where('rp_role_id', $user_data->u_role_id)->pluck('rp_url_id');
                // dd($role_url_access_id);

                if (sizeof($role_url_access_id) > 0) {
                    $urls = AuthUrl::where('url_visible', 1)->whereIn('url_id', $role_url_access_id)->get()->toArray();

                    $url_data = array_column($urls, 'url_name');
                    // dd($url_data);
                    if (in_array('admission/admission-list', $url_data)) {
                        $student_adm_list = Student::where('is_personal_save', 1)
                            ->orderBy('s_id', 'desc')
                            ->get()
                            ->map(function ($data) {
                                return [
                                    'form_num' => $data->s_appl_form_num,
                                    'name' => $data->s_candidate_name,
                                    'guardian_name' => $data->s_guardian_name,
                                    'phone_no' => $data->s_phone,
                                    'is_applied' => (bool)$data->is_personal_save,
                                    'is_paid' => (bool)$data->is_payment

                                ];
                            });
                        if (sizeof($student_adm_list) > 0) {
                            $reponse = array(
                                'error'     =>  false,
                                'message'   =>  'Student Admission list found',
                                'count'     =>   sizeof($student_adm_list),
                                'list'  =>   $student_adm_list
                            );
                            return response(json_encode($reponse), 200);
                        } else {
                            $reponse = array(
                                'error'     =>  true,
                                'message'   =>  'No student available'

                            );
                            return response(json_encode($reponse), 200);
                        }
                    } else {
                        return response()->json([
                            'error'     =>  true,
                            'message'   =>   "Oops! you don't have sufficient permission"
                        ], 403);
                    }
                } else {
                    return response()->json([
                        'error'     =>  true,
                        'message'   =>   "Oops! you don't have sufficient permission"
                    ], 403);
                }
            } else {
                return response()->json([
                    'error'     =>  true,
                    'message'   =>  'Unable to process your request due to invalid token'
                ], 401);
            }
        }
    }
}
