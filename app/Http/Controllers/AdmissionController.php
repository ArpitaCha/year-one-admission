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
use App\Models\HeadVerifierStudentAssign;
use App\Models\VerifierStudentAssign;
use App\Models\AuthUrl;
use Illuminate\Support\Facades\Http;




class AdmissionController extends Controller
{
    public function submitStudents(Request $request)
    {
        // return $request->all();
        try {
            $validated = Validator::make($request->all(), [
                'student_first_name'   => ['required'],
                // 'student_last_name'    => ['required'],
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
                // 'student_subdivision'  => ['required'],
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
                // 'student_block' => ['required'],
                // 'exam_qualifications' => 'required',
                'exam_board' => 'required',
                'exam_pass_yr' => 'required',
                'exam_total_marks' => 'required',
                'obtained_marks' => 'required',
                'exam_elgb_code' => 'required',
                'exam_school_name' => 'required',
                'exam_district' => 'required',
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
                $student_kanyashree_document_path = null;
                $student_kanyashree_no = null;
                if ($request->is_kanyashree === '1') {
                    if ($request->hasFile('student_kanyashree_document')) {
                        $document = $request->file('student_kanyashree_document');
                        $documentName = $s_appl_form_num . '_kanyashree_document.' . $document->getClientOriginalExtension();
                        $document->storeAs('uploads/', $documentName, 'public');
                        $student_kanyashree_document_path = 'uploads/' . $documentName;
                    } elseif (is_string($request->student_kanyashree_document)) {
                        $student_kanyashree_document_path =  $student->s_kanyashree_doc  ? $student->s_kanyashree_doc
                            : null;
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'Kanyashree document is mandatory when Kanyashree is selected.'
                        ], 400);
                    }
                    $student_kanyashree_no = $request->student_kanyashree_no ?? '';
                }


                if ($request->is_pwd === '1') {
                    if ($request->hasFile('student_pwd_document') && $request->file('student_pwd_document')->isValid()) {
                        $document = $request->file('student_pwd_document');
                        $documentName = $s_appl_form_num . '_pwd_document.' . $document->getClientOriginalExtension();
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
                    // EWS is not selected, so clear values
                    $student_pwd_document_path = null;
                    $pwd_certificate_number = '';
                    $pwd_certificate_issue_date = '';
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
                if ($request->is_ews === '1') {
                    if ($request->hasFile('student_ews_document') && $request->file('student_ews_document')->isValid()) {
                        $document = $request->file('student_ews_document');
                        $documentName = $s_appl_form_num . '_ews_document.' . $document->getClientOriginalExtension();
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

                if ($request->hasFile('student_aadhar_document')) {
                    $document = $request->file('student_aadhar_document');
                    $documentName = $s_appl_form_num . '_aadhar_document.' . $document->getClientOriginalExtension();
                    $document->storeAs('uploads/', $documentName, 'public');
                    $student_aadhar_document_path = 'uploads/' . $documentName;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'adhar document is mandatory.'
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
                if ($request->hasFile('bank_passbook_document')) {
                    $document = $request->file('bank_passbook_document');
                    $documentName = $s_appl_form_num . '_bank_passbook_document.' . $document->getClientOriginalExtension();
                    $document->storeAs('uploads/', $documentName, 'public');
                    $student_bank_passbook_document_path = 'uploads/' . $documentName;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'bank passbook document is mandatory.'
                    ], 400);
                }

                $bank_details = json_decode($request->bank_details, true);
                $middleName = trim($request->student_middle_name);
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
                    'ews_cert_number' => $ews_certificate_number,
                    'ews_cert_date' => $ews_certificate_issue_date,
                    'ews_valid_year' => $ews_valid_year,
                    'pc_cert_no' => $pwd_certificate_number,
                    'pc_cert_date' => $pwd_certificate_issue_date,
                    's_block' => $request->student_block,
                    's_adhar_doc' => $student_aadhar_document_path,
                    's_bank_details' => json_encode($bank_details),
                    's_bank_passbook_doc' => $student_bank_passbook_document_path


                ]);
                $exam_per_mrks = json_decode($request->exam_marks, true);
                // dd($exam_per_mrks);
                JexpoApplElgbExam::updateOrCreate(
                    [
                        'exam_appl_form_num' => $s_appl_form_num,
                    ],
                    [
                        'exam_elgb_code'    => $request->exam_elgb_code,
                        'exam_board'         => $request->exam_board,
                        'exam_pass_yr'       => $request->exam_pass_yr,
                        'exam_tot_marks'     => $request->exam_total_marks,
                        'exam_ob_marks'      => $request->obtained_marks,
                        'exam_school_name'   => $request->exam_school_name,
                        'exam_marks_type'    => $request->exam_marks_type ?? null,
                        'exam_per_marks'     => json_encode($exam_per_mrks),
                        'exam_state_code'         => $request->exam_state,
                        'exam_district'      => $request->exam_district,
                        'updated_at'         => now(),
                    ]
                );
                auditTrail(
                    $s_appl_form_num,
                    "{$request->student_first_name} {$request->student_last_name} has successfully inserted profile at {$student->s_phone} on {$now}.",
                    'insert'
                );




                return response()->json([
                    'success' => true,
                    'message' => 'Application submitted successfully',
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
        $roleId = $request->auth_role_id ?? null;
        $userData = $request->auth_user ?? null;
        if ($roleId == 1) {
            $students = Student::where('is_personal_save', 1)
                ->orderBy('s_id', 'desc')
                ->get();
        } elseif (in_array($roleId, [3, 4])) {
            $students = Student::where('is_personal_save', 1)
                ->where('s_home_district', $userData->u_inst_district ?? null)
                ->orderBy('s_id', 'desc')
                ->get();
        } else {
            $students = collect(); // empty collection for other roles
        }

        $student_adm_list = $students->map(function ($data) {
            return [
                'form_num'      => $data->s_appl_form_num,
                'name'          => $data->s_candidate_name,
                'guardian_name' => $data->s_guardian_name,
                'phone_no'      => $data->s_phone,
                'is_applied'    => (bool)$data->is_personal_save,
                'is_paid'       => (bool)$data->is_payment,
                'is_approved'   => (bool)$data->is_approved,
                'is_reject'     => (bool)$data->is_reject,
                'remarks'       => $data->s_remarks ?: '',
                'overall_status' => (function () use ($data) {
                    if (!$data->is_personal_save) return 'Not Applied';
                    if ($data->is_personal_save && !$data->is_payment) return 'Applied but Not Paid';
                    if ($data->is_payment && !$data->is_approved && !$data->is_reject) return 'Paid but Not Approved';
                    if ($data->is_reject) return 'Rejected';
                    if ($data->is_approved) return 'Approved';
                    return 'Pending';
                })(),
            ];
        });

        return response()->json([
            'error'   => false,
            'message' => 'Data fetched successfully',
            'list'    => $student_adm_list
        ], 200);
    }

    public function approveCouncil(Request $request)
    {
        $user_id = $request->auth_user_id ?? null;
        $form_num = $request->form_num;
        $remarks = $request->remarks;
        $status   = null;
        $message  = null;
        if ($request->is_approve) {
            $checked = Student::where('s_appl_form_num', $form_num)->where('is_personal_save', 1)->Update([
                'is_approved' => (bool)$request->is_approve,
                'is_reject'   => null,
                's_remarks'   => null
            ]);
            $message = "Admission Approved Successfully";
            $status = "APPROVED";
        } elseif ($request->is_reject) {
            $checked = Student::where('s_appl_form_num', $form_num)->where('is_personal_save', 1)->Update([
                'is_reject' => (bool)$request->is_reject,
                'is_approved'   => null,
                's_remarks' => $request->remarks
            ]);
            $status = "REJECTED";
            $message = "Admission rejected";
        }
        auditTrail($user_id, "$form_num, $status - Added successfully");
        return response()->json([
            'error' => false,
            'message' => $message
        ]);
    }
    public function verifierList(Request $request)
    {
        $roleId = $request->auth_role_id ?? null;
        $userData = $request->auth_user ?? null;

        if ($roleId == 1) {
            $list = SuperUser::whereNotIn('u_role_id', [1])->with('role', 'district')->get()->map(function ($data) {
                return [
                    'institute' => [
                        'inst_code'   => $data->u_inst_code ?? '',
                        'inst_name' => $data->u_inst_name ?? '',
                    ],
                    'phone_no' => $data->u_phone,
                    'name' => $data->u_fullname,
                    'email' => $data->u_email,
                    'username' => $data->u_username,
                    'district' => [
                        'district_id'   => optional($data->district)->district_id_pk,
                        'district_name' => optional($data->district)->district_name,
                    ],
                    'role'      => [
                        'role_id'   => $data->u_role_id ?? '',
                        'role_name' => optional($data->role)->role_name,
                    ],
                ];
            });
        } elseif ($roleId == 3) {
            $list = SuperUser::where('u_role_id', 4)->where('u_inst_code', $userData->u_inst_code)->where('u_inst_district', $userData->u_inst_district)->with('role', 'district')->get()->map(function ($data) {
                return [
                    'institute' => [
                        'inst_code'   => $data->u_inst_code ?? '',
                        'inst_name' => $data->u_inst_name ?? '',
                    ],
                    'phone_no' => $data->u_phone,
                    'name' => $data->u_fullname,
                    'email' => $data->u_email,
                    'username' => $data->u_username,
                    'district' => [
                        'district_id'   => optional($data->district)->district_id_pk,
                        'district_name' => optional($data->district)->district_name,
                    ],
                    'role'      => [
                        'role_id'   => $data->u_role_id ?? '',
                        'role_name' => optional($data->role)->role_name,
                    ],
                ];
            });
        }
        if (sizeof($list) > 0) {
            $reponse = array(
                'error'     =>  false,
                'message'   =>  'list found',
                'count'     =>   sizeof($list),
                'list'  =>   $list
            );
            return response(json_encode($reponse), 200);
        } else {
            $reponse = array(
                'error'     =>  true,
                'message'   =>  'No data available'

            );
            return response(json_encode($reponse), 200);
        }
    }
    public function addVerifier(Request $request)
    {
        // Validation
        $validated = Validator::make($request->all(), [
            'phone_no' => 'required',
            'name'     => 'required',
            'email'    => 'required|email',
            'inst_code' => 'required',
            'role_id'  => 'required|in:3,4', // only allow Head Verifier(3) or Verifier(4)

        ]);

        if ($validated->fails()) {
            return response()->json([
                'error'   => true,
                'message' => $validated->errors()->first()
            ], 422);
        }

        // Normalize name
        $normalizedName = preg_replace('/\s+/', ' ', trim($request->name));
        $instName = Institute::where('i_code', $request->inst_code)->value('i_name');

        // Check if phone already exists for the role
        $roleId = $request->role_id;
        if (SuperUser::where('u_role_id', $roleId)->where('u_phone', $request->phone_no)->exists()) {
            $message = $roleId == 3 ? 'Head Verifier already exists with this phone number' : 'Verifier already exists with this phone number';
            return response()->json(['error' => true, 'message' => $message], 400);
        }

        // Check if normalized name already exists for the role
        if (SuperUser::where('u_role_id', $roleId)
            ->whereRaw('LOWER(REGEXP_REPLACE(u_fullname, \'\\s+\', \' \', \'g\')) = ?', [strtolower($normalizedName)])
            ->exists()
        ) {
            $message = $roleId == 3 ? 'Head Verifier already exists with this name' : 'Verifier already exists with this name';
            return response()->json(['error' => true, 'message' => $message], 400);
        }

        // Create the user
        $superUser = SuperUser::create([
            'u_fullname'      => $normalizedName,
            'u_email'         => $request->email,
            'u_username'      => $request->username,
            'u_phone'         => $request->phone_no,
            'u_inst_name'     => $instName,
            'u_inst_code'     => $request->inst_code,
            'u_role_id'       => $roleId,
            'u_inst_district' => $request->district,
            'created_at'      => now()
        ]);

        if ($superUser) {
            return response()->json([
                'error'   => false,
                'message' => 'User created successfully',
                'data'    => $superUser
            ]);
        }

        return response()->json([
            'error'   => true,
            'message' => 'Something went wrong while creating the user'
        ], 500);
    }

    public function updateVerifier(Request $request)
    {
        // Validation
        $validated = Validator::make($request->all(), [
            'phone_no' => 'required',
            'name'     => 'required',
            'email'    => 'required|email',
            'inst_code' => 'required',
            'role_id'  => 'required|in:3,4', // ensure role is valid

        ]);

        if ($validated->fails()) {
            return response()->json([
                'error'   => true,
                'message' => $validated->errors()->first()
            ], 422);
        }

        // Normalize name
        $normalizedName = preg_replace('/\s+/', ' ', trim($request->name));
        $instName = Institute::where('i_code', $request->inst_code)->value('i_name');

        // Find user by phone
        $existingUser = SuperUser::where('u_phone', $request->phone_no)->first();

        if (!$existingUser) {
            return response()->json([
                'error'   => true,
                'message' => 'User not found'
            ], 404);
        }

        // Check if normalized name already exists for the role (excluding current user)
        $roleId = $request->role_id;
        if (SuperUser::where('u_role_id', $roleId)
            ->whereRaw('LOWER(REGEXP_REPLACE(u_fullname, \'\\s+\', \' \', \'g\')) = ?', [strtolower($normalizedName)])
            ->where('u_id', '!=', $existingUser->u_id)
            ->exists()
        ) {
            $message = $roleId == 3 ? 'Head Verifier already exists with this name' : 'Verifier already exists with this name';
            return response()->json(['error' => true, 'message' => $message], 400);
        }

        // Update user
        $existingUser->update([
            'u_fullname'      => $normalizedName,
            'u_email'         => $request->email,
            'u_username'      => $request->username,
            'u_phone'         => $request->phone_no,
            'u_inst_name'     => $instName,
            'u_inst_code'     => $request->inst_code,
            'u_role_id'       => $roleId,
            'u_inst_district' => $request->district,
            'updated_at'      => now()
        ]);

        return response()->json([
            'error'   => false,
            'message' => 'User updated successfully',
            'data'    => $existingUser
        ]);
    }

    public function checkValidationFields(Request $request, $role)
    {
        try {
            if ($role === 'COUNCIL') {
                return response()->json([
                    'error'   => false,
                    'message' => 'All Validation passed',
                    'data'    => []
                ]);
            }

            $validated = Validator::make($request->all(), [
                'student_phone'      => 'required',
                'student_first_name' => 'required',
                'student_last_name'  => 'required',
                'student_email'      => 'required|email',
            ]);

            if ($validated->fails()) {
                return response()->json([
                    'error'   => true,
                    'message' => $validated->errors()->first()
                ], 422);
            }

            return response()->json([
                'error'   => false,
                'message' => 'Validation passed'
            ]);
        } catch (\Exception $e) {
            // Catch any unexpected exception
            return response()->json([
                'error'   => true,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }
    public function getBranchByIfsc(Request $request, $ifsc)
    {
        $ifsc = strtoupper($request->ifsc);
        $response = Http::get("https://ifsc.razorpay.com/{$ifsc}");

        if ($response->successful()) {
            $data = $response->json();
            return response()->json([
                'bank'   => $data['BANK'] ?? null,
                'branch' => $data['BRANCH'] ?? null,
                'address' => $data['ADDRESS'] ?? null,
            ]);
        }

        return response()->json(['error' => 'Invalid IFS code'], 400);
    }
    public function districtWiseVerifier(Request $request)
    {
        $role_id = $request->auth_role_id;
        $district_id = $request->district_id;
        if ($role_id == 1) {
            $today = now();

            // Get all head verifiers in the district
            $verifiers = SuperUser::with(['HeadVerifierStudentAssign' => function ($query) use ($district_id) {
                $query->where('dist_id', $district_id);
            }, 'institute'])
                ->select('u_id', 'u_fullname', 'u_inst_code')
                ->where('u_role_id', 3)
                ->where('u_inst_district', $district_id)
                ->get();

            // Build verifier list with student count
            $verifier_list = $verifiers->map(function ($verifier) use ($district_id) {
                $student_count = HeadVerifierStudentAssign::where('dist_id', $district_id)
                    ->where('head_verifier_id', $verifier->u_id)
                    ->count();

                return [
                    'verifier_id'        => $verifier->u_id,
                    'verifier_name'      => $verifier->u_fullname,
                    'verifier_inst_name' => optional($verifier->institute)->i_name,
                    'student_count'      => $student_count,
                ];
            });


            $count = $verifier_list->count();


            $student_list = Student::where('s_home_district', $district_id)
                ->where('is_payment', 1)
                ->count();


            $assign_student_list = Student::where('s_home_district', $district_id)
                ->where('is_assign', 1)
                ->count();

            // Remaining students
            $left_student_list = $student_list - $assign_student_list;

            return response()->json([
                'error' => false,
                'distribution' => $verifier_list,
                'count' => $count,
                'student_count' => $student_list,
                'assigned_student_count' => $assign_student_list,
                'left_student_count' => $left_student_list
            ]);
        } elseif ($role_id == 3) {
            $authUserId = $request->auth_user_id;

            // Get all verifiers under this head verifier's district
            $verifierList = SuperUser::with('institute')
                ->select('u_id', 'u_fullname')
                ->where('u_role_id', 4) // Regular verifier
                ->where('u_inst_district', $district_id)
                ->get()
                ->map(function ($verifier) use ($authUserId, $district_id) {
                    $studentCount = VerifierStudentAssign::where([
                        ['verifier_id', '=', $verifier->u_id],
                        ['head_verifier_id', '=', $authUserId],
                        ['dist_id', '=', $district_id],
                    ])
                        ->count();

                    return [
                        'verifier_id'   => $verifier->u_id,
                        'verifier_name' => $verifier->u_fullname,
                        'student_count' => $studentCount,
                        // Optional: include institute name if needed
                        'institute_name' => $verifier->institute->name ?? null,
                    ];
                });

            $count = $verifierList->count(); // total verifiers under head

            // Total students assigned to this head verifier
            $student_list = HeadVerifierStudentAssign::where('dist_id', $district_id)
                ->where('head_verifier_id', $authUserId)
                ->count();

            // Total students distributed (assigned + distributed)
            $assignStudentCount = HeadVerifierStudentAssign::where('head_verifier_id', $authUserId)
                ->where('dist_id', $district_id)
                ->whereHas('student', function ($query) {
                    $query->where('is_assign', 1)
                        ->where('is_distribute', 1);
                })
                ->count();

            // Remaining students under this head verifier
            $left_student_list = $student_list - $assignStudentCount;

            return response()->json([
                'error' => false,
                'distribution' => $verifierList,
                'count' => $count,
                'student_count' => $student_list,
                'assigned_student_count' => $assignStudentCount,
                'left_student_count' => $left_student_list
            ]);
        }
    }

    public function districtWiseAssign(Request $request)
    {
        $districtId = $request->district_id;
        $role_id    = $request->auth_role_id;

        if ($role_id == 1) {

            $districtId = $request->input('district_id');

            // Step 1: Fetch unassigned, paid students from the district
            $students = Student::where('s_home_district', $districtId)
                ->where('is_payment', 1)
                ->where('is_assign', 0)
                ->pluck('s_appl_form_num');

            if ($students->isEmpty()) {
                return response()->json(['message' => 'No unassigned students found'], 404);
            }

            // Step 2: Fetch head verifiers from the district
            $verifiers = SuperUser::where('u_role_id', 3)
                ->where('u_inst_district', $districtId)
                ->get(['u_id', 'u_fullname', 'u_inst_code']);

            if ($verifiers->isEmpty()) {
                return response()->json(['message' => 'No head verifiers found'], 404);
            }

            // Step 3: Calculate distribution
            $totalStudents  = $students->count();
            $totalVerifiers = $verifiers->count();
            $perVerifier    = intdiv($totalStudents, $totalVerifiers);
            $extra          = $totalStudents % $totalVerifiers;

            $distribution   = [];
            $studentIndex   = 0;

            // Step 4: Assign students to verifiers
            foreach ($verifiers as $index => $verifier) {
                $count = $perVerifier + ($index < $extra ? 1 : 0);
                $assignedStudents = $students->slice($studentIndex, $count);

                if ($assignedStudents->isEmpty()) {
                    continue;
                }

                // Insert assignments
                foreach ($assignedStudents as $formNum) {
                    HeadVerifierStudentAssign::create([
                        'head_verifier_id'   => $verifier->u_id,
                        'dist_id'            => $districtId,
                        'inst_code'          => $verifier->u_inst_code,
                        'student_form_num'   => $formNum,
                        'created_at'         => now(),
                    ]);
                }

                // Update student assignment status
                Student::whereIn('s_appl_form_num', $assignedStudents)
                    ->update(['is_assign' => 1]);

                // Track distribution summary
                $distribution[] = [
                    'verifier_id'   => $verifier->u_id,
                    'verifier_name' => $verifier->u_fullname,
                    'student_count' => $assignedStudents->count(),
                ];

                $studentIndex += $count;
            }

            // Step 5: Return response
            return response()->json([
                'message'         => 'Students assigned successfully to head verifiers',
                'total_students'  => $totalStudents,
                'total_verifiers' => $totalVerifiers,
                'distribution'    => $distribution,
            ]);
        } elseif ($role_id == 3) {
            $user_id = $request->auth_user_id ?? null;

            // Step 1: Validate Head Verifier
            $headVerifier = SuperUser::where('u_id', $user_id)
                ->where('u_role_id', 3)
                ->first();

            if (!$headVerifier) {
                return response()->json(['message' => 'Invalid Head Verifier'], 404);
            }

            // Step 2: Fetch child verifiers under same institution and district
            $childVerifiers = SuperUser::where('u_role_id', 4)
                ->where('u_inst_code', $headVerifier->u_inst_code)
                ->where('u_inst_district', $districtId)
                ->get(['u_id', 'u_fullname', 'u_inst_code']);

            if ($childVerifiers->isEmpty()) {
                return response()->json(['message' => 'No verifiers found under this head verifier'], 404);
            }

            // Step 3: Fetch students assigned to head verifier but not yet distributed
            $students =   HeadVerifierStudentAssign::where('head_verifier_id', $user_id)
                ->where('dist_id', $districtId)
                ->whereHas('student', function ($query) {
                    $query->where('is_assign', 1)
                        ->where('is_distribute', 0);
                })
                ->pluck('student_form_num');

            if ($students->isEmpty()) {
                return response()->json(['message' => 'No students to distribute'], 404);
            }

            // Step 4: Calculate distribution
            $totalStudents  = $students->count();
            $totalVerifiers = $childVerifiers->count();
            $perVerifier    = intdiv($totalStudents, $totalVerifiers);
            $extra          = $totalStudents % $totalVerifiers;

            $distribution = [];
            $studentIndex = 0;

            // Step 5: Distribute students to child verifiers
            DB::beginTransaction();

            try {
                foreach ($childVerifiers as $index => $verifier) {
                    $count = $perVerifier + ($index < $extra ? 1 : 0);
                    $assignedStudents = $students->slice($studentIndex, $count)->values();

                    if ($assignedStudents->isEmpty()) {
                        continue;
                    }


                    foreach ($assignedStudents as $formNum) {
                        VerifierStudentAssign::create([
                            'head_verifier_id' => $user_id,
                            'verifier_id'      => $verifier->u_id,
                            'dist_id'          => $districtId,
                            'inst_code'        => $verifier->u_inst_code,
                            'student_form_num' => $formNum,
                            'created_at'       => now(),
                        ]);
                    }

                    Student::whereIn('s_appl_form_num', $assignedStudents)
                        ->update(['is_distribute' => 1]);

                    $distribution[] = [
                        'verifier_id'   => $verifier->u_id,
                        'verifier_name' => $verifier->u_fullname,
                        'student_count' => $assignedStudents->count(),
                    ];

                    $studentIndex += $count;
                }

                DB::commit();

                // Step 6: Return response
                return response()->json([
                    'message'         => 'Students distributed successfully to verifiers',
                    'total_students'  => $totalStudents,
                    'total_verifiers' => $totalVerifiers,
                    'distribution'    => $distribution,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Distribution failed',
                    'error'   => $e->getMessage(),
                ], 500);
            }
        }
    }
    public function otherdistrictverifier(Request $request)
    {
        $today = now();

        // Force district logic to Howrah if another district is passed
        $howrah_id = '15';
        $effective_district_id = ($district_id == $howrah_id) ? $district_id : $howrah_id;

        // Get all head verifiers in the effective district
        $verifiers = SuperUser::with([
            'HeadVerifierStudentAssign' => function ($query) use ($effective_district_id) {
                $query->where('dist_id', $effective_district_id);
            },
            'institute',
            'district'
        ])
            ->select('u_id', 'u_fullname', 'u_inst_code', 'u_inst_district')
            ->where('u_role_id', 3)
            ->where('u_inst_district', $effective_district_id)
            ->get();
        $verifier_list = $verifiers->map(function ($verifier) use ($effective_district_id) {
            $student_count = HeadVerifierStudentAssign::where('dist_id', $effective_district_id)
                ->where('head_verifier_id', $verifier->u_id)
                ->count();


            // Total students distributed (assigned + distributed)
            $assignStudentCount = HeadVerifierStudentAssign::where('head_verifier_id', $authUserId)
                ->where('dist_id', $effective_district_id)
                ->whereHas('student', function ($query) {
                    $query->where('is_assign', 1)
                        ->where('is_distribute', 1);
                })
                ->count();
            $left_student_list = $student_count - $assignStudentCount;

            return [
                'verifier_id'        => $verifier->u_id,
                'verifier_name'      => $verifier->u_fullname,

                'student_count'      => $student_count,
                'assigned_student_count' => $assignStudentCount,
                'left_student_count' => $left_student_list,
            ];
        });
    }
}
