<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Jexpo Application Form</title>
<style>
   body {
        font-family: Arial, sans-serif;
        font-size: 13px;
        margin: 8px;
        border: 2px solid #000; /* Outer border */
        padding: 10px;
        background-color: antiquewhite;
       
    }

    table {
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 10px;
    }

    td, th {
        border: 1px solid #000;
        padding: 5px;
        vertical-align: top;
    }

    .no-border {
        border: none;
    }

    .center {
        text-align: center;
    }

    .header {
        font-size: 16px;
        font-weight: bold;
    }

    .photo {
        width: 120px;
        height: 100px;
        border: 1px solid #000;
        background: #eee;
        text-align: center;
        font-size: 12px;
    }

    .qr {
        width: 120px;
        height: 120px;
        border: 1px solid #000;
        background: #eee;
        text-align: center;
        font-size: 12px;
    }

    .logo-container {
        width: 10%;
        text-align: center;
    }

    .header {
        position: fixed;
        top: 1px;
        text-align: center;
    }
</style>

</head>
<body>

    <div class="header" style="position:fixed;">
        <div class="logo-container"style="position:absolute;margin-top:10px;margin-left:5px;">

                 <img 
                        src="{{ public_path('storage/uploads/council_logo.png') }}" 
                        alt="Council Logo" 
                        style="width:70px; height:auto;  display:block; margin-top:20px; margin-left:auto; margin-right:10px;">
        </div>
        <div class="header-text" style="flex-grow: 1;">
            <p style="line-height:1;">
                <span style="color:#2d0660;font-family:Cambria;font-size:12px;">
                    <span style="font-stretch:115%;">
                        <strong>WEST BENGAL STATE COUNCIL OF TECHNICAL & VOCATIONAL EDUCATION AND SKILL
                            DEVELOPMENT</strong>
                    </span>
                </span>
            </p>

            <p style="line-height:11.53px;margin:0px 130.13px 0px 128.93px;text-align:center;text-indent:0px;">
                <span style="font-family:'Trebuchet MS', Helvetica, sans-serif;font-size:12px;">
                    {Erstwhile West Bengal State Council of Technical Education}
                </span>
            </p>
            <p style="line-height:11.53px;margin:0px 130.13px 0px 128.93px;text-align:center;text-indent:0px;">
                <span style="font-family:'Trebuchet MS', Helvetica, sans-serif;font-size:12px;">
                    (A Statutory Body under Government of West Bengal Act XXVI of 2013)
                </span>
            </p>

            <p style="line-height:11.53px;margin:0px 130.13px 0px 128.93px;text-align:center;text-indent:0px;">
                <span style="font-family:'Trebuchet MS', Helvetica, sans-serif;font-size:10px;">
                    Karigari Bhavan, 4th Floor, Plot No. B/7, Action Area-III, Newtown, Rajarhat, Kolkata–700160
                </span>
            </p>

        </div>



    </div>
    <div>
        <p style="text-align: center; font-size: 22px; font-weight: bold;padding-top:55px;">Application Details</p>
    </div>


    

<table border="1" style="border-collapse:collapse; width:100%;">
    <tr>
        {{-- Left side: Application Number --}}
        <td style="font-size: 20px; width:20%;">
            Application Number:
        </td>
        <td style="font-size: 20px; width:25%;font-weight:bold;">
            <span>{{ $students->s_appl_form_num }}</span>
        </td>

        {{-- Right side: Photo + Signature stacked --}}
        @if(!empty($students->s_photo) || !empty($students->s_sign))
            <td style="text-align:center; vertical-align:top; width:25%;">
                {{-- Photo --}}
                @if(!empty($students->s_photo))
                    <img 
                        src="{{ public_path('storage/' . $students->s_photo) }}" 
                        alt="Student Photo" 
                        style="width:100px; height:auto; border:1px solid #000; display:block; margin:0 auto 10px;">
                @endif

                {{-- Signature --}}
                @if(!empty($students->s_sign))
                    <img 
                        src="{{ public_path('storage/' . $students->s_sign) }}" 
                        alt="Student Signature" 
                        style="width:140px; height:50px; border:1px solid #000; display:block; margin:0 auto;">
                @endif
            </td>
        @endif
    </tr>
</table>

    <table>
         <tr>
        <th colspan="4" style="text-align:left; font-size:16px;">Personal Details</th>
    </tr>

    <tr>
        <td><strong>  Candidate's Name:</strong></td>
        <td colspan="3">{{ $students->s_candidate_name }}</td>
    </tr>
     <tr>
        <td><strong>Father’s Name</strong></td>
        <td>{{ $students->s_father_name }}</td>
        <td><strong>Mother’s Name</strong></td>
        <td>{{ $students->s_mother_name }}</td>
    </tr>
    <tr>
        <td><strong>Date of Birth</strong></td>
        <td>{{ \Carbon\Carbon::parse($students->s_dob)->format('d-m-Y') }}</td>
        <td><strong>Gender</strong></td>
        <td>{{ $students->s_gender }}</td>
    </tr>
   
    <tr>
        <td><strong>Email No.</strong></td>
        <td>{{ $students->s_email }}</td>
        <td><strong>Mobile Number</strong></td>
        <td>{{ $students->s_phone }}</td>
    </tr>
    <tr>
        <td><strong>Caste</strong></td>
        <td>{{ $students->s_caste }}@if(strtolower($students->s_caste) !== 'general')
                (
                <strong>Cert No:</strong> {{ $students->cast_cert_number ?? 'N/A' }},
                <strong>Date:</strong> {{ $students->cast_cert_date ?? 'N/A' }},
                <strong>Subcategory:</strong> {{ $students->s_caste_subcategory ?? 'N/A' }}
                )
            @endif</td>
        <td><strong>EWS</strong></td>
        <td>  {{ $students->s_ews == 1 ? 'Yes' : 'No' }}
                
                @if($students->s_ews == 1)
                    (
                    <strong>Cert No:</strong> {{ $students->ews_cert_number ?? 'N/A' }},
                    <strong>Date:</strong> {{ $students->ews_cert_date ?? 'N/A' }}
                    )
                @endif</td>
    </tr>
    <tr>
        <td><strong>Religion</strong></td>
        <td>{{ $students->s_religion }}</td>
        <td><strong>Marital Status</strong></td>
        <td>{{ $students->is_married == 1 ? 'Married' : 'Unmarried' }}</td>
    </tr>
   
    
   
</table>
<table>
     <tr>
        <th colspan="4" style="text-align:left; font-size:16px;">Address Details</th>
    </tr>
    <tr>
        <td colspan="2"><strong>Address</strong></td>
        <td colspan="2">{{ $students->s_address }}</td>
    </tr>
    <tr>
        <td style="width: 50%;"><strong>State</strong></td>
        <td style="width: 50%;">{{ $students->state->state_name ?? 'N/A' }}</td>
        <td style="width: 50%;"><strong>District</strong></td>
        <td style="width: 50%;">{{ $students->district->district_name ?? 'N/A' }}</td>
    </tr>
  
    <tr>
        <td><strong>Subdivision</strong></td>
        <td>{{ $students->subdivision->name ?? 'N/A' }}</td>
     
        <td><strong>Pin No</strong></td>
        <td>{{ $students->s_pin_no }}</td>
    </tr>
    
</table>


<table>
    <tr>
        <th colspan="5" style="text-align:left; font-size:16px;">Payment Details</th>
    </tr>
    <tr>
         <td><strong>Payment mode</strong></td>
       
     
        <td><strong>Merchant Order no</strong></td>
         <td><strong>Payment Date</strong></td>
         <td><strong>Transaction Id</strong></td>
        <td><strong>Transaction Amount</strong></td>

    </tr>
    <tr>
         <td>{{ $payment->trans_mode ?? 'N/A'}}</td>

        <td>{{ $payment->marchnt_id ?? 'N/A'}}</td>

        <td>{{ $payment->trans_time ?? 'N/A'}}</td>
        <td>{{ $payment->trans_id ?? 'N/A'}}</td>
        <td>{{ $payment->trans_amount ?? 'N/A'}}</td>

    </tr>
     {{-- <tr>
         <td>Online</td>
         
        <td>hjiojiokko</td>
        
        <td>13-07-25</td>
        <td>ghjjhkjkj+523</td>
        <td>500</td>

    </tr> --}}
</table>
<table>
     <tr>
        <th colspan="5" style="text-align:left; font-size:16px;">Bank Details For Refund fee</th>
    </tr>
    {{-- <h3><strong>Bank Details For Refund fee:</strong></h3> --}}
    <tr>
         <td><strong>Bank name</strong></td>
         <td><strong>Account Number</strong></td>
         <td><strong>IFSC Code</strong></td>
         <td><strong>Branch</strong></td>
        <td><strong>Account Holder Name</strong></td>

    </tr>
    <tr>
         <td>{{ $bank_details['bankName'] ?? 'N/A' }}</td>
         <td>{{ $bank_details['accNumber'] ?? 'N/A' }}</td>
         <td>{{ $bank_details['IFSC'] ?? 'N/A' }}</td>
         <td>{{ $bank_details['bankBranchName'] ?? 'N/A' }}</td>
         <td>{{ $bank_details['accHolderName'] ?? 'N/A' }}</td>

    </tr>
     
</table>
<table style="padding-top:90px;">
    <tr>
        <th colspan="5" style="text-align:left; font-size:16px;">Qualification Details</th>
    </tr>
    <tr>
    <td><strong>Qualification</strong></td>
    <td><strong>School Name</strong></td>
    <td><strong>Year of Passing</strong></td>
    <td><strong>Board</strong></td>
    <td><strong>Overall %</strong></td>
</tr>

<tr>
    <td>{{ $education->exam_elgb_code }}</td>
    <td>{{ $education->exam_school_name }}</td>
    <td>{{ $education->exam_pass_yr }}</td>
    <td>{{ $education->board->board_name }}</td>
    <td>
        @if(!empty($education->exam_ob_marks) && !empty($education->exam_tot_marks) && $education->exam_tot_marks > 0)
            {{ number_format(($education->exam_ob_marks / $education->exam_tot_marks) * 100, 2) }}%
        @else
            N/A
        @endif
    </td>
</tr>

{{-- Show subject-wise marks --}}
@if(!empty($subjects) && is_array($subjects))
    <tr>
        <td colspan="5">
            <table  cellpadding="4" style="width:100%; margin-top:5px;margin-left:0px; margin-right: 8px; margin-bottom: 2px;">
                <tr>
                    <th>Subject</th>
                    <th>Total Marks</th>
                    <th>Obtained Marks</th>
                    <th>Percentage</th>
                </tr>
                @foreach($subjects as $sub)
                    <tr>
                        <td style="">{{ $sub['subject'] }}</td>
                        <td>{{ $sub['total'] }}</td>
                        <td>{{ $sub['obtained'] }}</td>
                        <td>
                            @if(!empty($sub['total']) && $sub['total'] > 0)
                                {{ number_format(($sub['obtained'] / $sub['total']) * 100, 2) }}%
                            @else
                                N/A
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
@endif

    {{-- Only show subject table for 10th --}}
    
</table>

<table>
   <tr>
        <th colspan="2" style="text-align:left; font-size:16px;">Ex-Serviceman Details</th>
    </tr>
    <tr>
        <td style="width:80%;"><strong>Are you an Ex-serviceman?</strong></td>
        <td>{{ $students->s_exsm == 1 ? 'Yes' : 'No'  }}</td>

    </tr>
</table>
<table>
    <tr>
        <th colspan="2" style="text-align:left; font-size:16px;">LLQ Details</th>
    </tr>
   
    <tr>
        <td style="width:80%;"><strong>Are you an LLQ?</strong></td>
        <td>{{ $students->s_llq == 1 ? 'Yes' : 'No'  }}</td>

    </tr>
</table>
<table>
      <tr>
        <th colspan="2" style="text-align:left; font-size:16px;">TFW Details</th>
    </tr>
   
    <tr>
        <td style="width: 80%;"><strong>Are you a TFW?</strong></td>
        <td>{{ $students->s_tfw == 1 ? 'Yes' : 'No'  }}</td>

    </tr>
</table>

    <h3><strong>Declaration:</strong></h3>
    <div>
       
            I hereby declare that the information provided above is true to the best of my knowledge and belief. I understand that any false information may lead to disqualification from the admission process.
   
    </div>
   <table style="width:100%; border:none; border-collapse:collapse;">
    <tr style="border:none;">
        {{-- Left side: Label --}}
        <td style="width:50%; text-align:left; vertical-align:middle; border:none;">
    <strong>Date:</strong> {{ date('d-m-Y') }} <br>
    <strong>Place:</strong> {{ $students->s_address ?? 'N/A' }}
</td>


        {{-- Right side: Signature --}}
        <td style="width:50%; text-align:right; vertical-align:middle; border:none;">
            @if(!empty($students->s_sign))
                <img src="{{ public_path('storage/' . $students->s_sign) }}" 
                     alt="Signature" 
                     style="width:150px; height:auto;">
            @else
                No Signature Provided
            @endif
        </td>
    </tr>
</table>


   


</body>
</html>
