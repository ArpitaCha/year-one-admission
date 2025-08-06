<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Application Form</title>
    <style>
        body {
            background-image: url("assets/logo_bg.png");
            background-position: center;
            background-repeat: no-repeat;
            background-size: 35%;
            font-family: Arial, sans-serif;
            margin: 0;

        }

        .left {
            float: left;
            margin-right: 20px;
            margin-left: 15px;
        }

        .right {
            float: right;
            margin-right: 50px;
        }

        .header {
            text-align: center;
            border-style: double;
        }

        .main-section {
            height: 350px;
            position: relative;
            /* margin-top: 20px;  */
        }

        .logo-container-right img {
            width: 80px;
            display: block;
            margin: 0 auto;
        }

        .logo-container-right {
            width: 20%;
            float: right;
        }

        .logo-container {
            width: 10%;
            text-align: left;
        }

        .logo-container img {
            width: 50px;
            display: block;
            margin: 0 auto;
        }

        /* .test td{
             border: 1px solid black;
        } */
        .center-horizontally {
            margin-left: auto;
            margin-right: auto;
            text-align: center;
        }

        .center-both {
            display: flex;
            justify-content: center;
            /* Center horizontally */
            align-items: center;
            /* Center vertically */
            height: 100vh;
            /* Full height of the viewport */
        }

        .rectangle {
            width: 150px;
            height: 150px;

            background-color: white;
            border: 2px solid black;
            margin-left: 17px;
            margin-top: 20px;
        }

        .rectangle1 {
            width: 180px;
            height: 50px;
            position: center;
            background-color: white;
            border: 2px solid black;
            margin-right: 10px;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>

<body>

    <div class="header" style="position:relative;">
        <div class="logo-container"style="position:absolute;margin-top:10px;margin-left:5px;">
            <img src="images/logo.png" alt="Left Logo">
        </div>
        <div class="header-text" style="text-align: center;flex-grow: 1;">
            <p style="line-height:1;margin:10.13px 130.27px 0px 128.93px;text-align:center;">
                <span style="color:#2d0660;font-family:Cambria;font-size:14px;">
                    <span style="font-stretch:115%;">
                        <strong>WEST BENGAL STATE COUNCIL OF TECHNICAL & VOCATIONAL EDUCATION AND SKILL
                            DEVELOPMENT</strong>
                    </span>
                </span>
            </p>

            <p style="line-height:11.53px;margin:0px 130.13px 0px 128.93px;text-align:center;text-indent:0px;">
                <span style="font-family:'Trebuchet MS', Helvetica, sans-serif;font-size:10px;">
                    {Erstwhile West Bengal State Council of Technical Education}
                </span>
            </p>
            <p style="line-height:11.53px;margin:0px 130.13px 0px 128.93px;text-align:center;text-indent:0px;">
                <span style="font-family:'Trebuchet MS', Helvetica, sans-serif;font-size:10px;">
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

    <div class="main-section" style="border:1px solid black;">
        <div>
            <h2 style="text-align:center;">Application Fees Money-receipt</h2>


            <div class="right">
                <label style="left:200px;">Dated: {{ date('d/m/Y') }}</label>
            </div>
        </div>
        <div style="width:100%; position:absolute;">
            <table border="0" style="width:70%;padding:3px;">
                <tbody>
                    <tr>
                        <td>Application Form No:</td>
                        <td>{{ $students->s_appl_form_num }}</td>
                    </tr>
                    <tr>
                        <td>Name Of The Candidate:</td>
                        <td>{{ $students->s_candidate_name }}</td>
                    </tr>
                    <tr>
                        <td>Date Of Birth:</td>
                        <td>{{ $students->s_dob }}</td>
                    </tr>
                    <tr>
                        <td>Guardian's Name:</td>
                        <td>{{ $students->s_guardian_name }}</td>
                    </tr>
                    <tr>
                        <td>Mobile No:</td>
                        <td>{{ $students->s_phone }}</td>
                    </tr>
                    <tr>
                        <td>Category:</td>
                        <td>{{  $students->s_caste == 1 ? 'No' : 'Yes' }}</td>
                    </tr>
                    <tr>
                        <td>Physical Challenged</td>
                        <td>{{ $students->s_pwd == 1 ? 'No' : 'Yes' }}</td>
                    </tr>
                    <tr>
                        <td>Applied Institute</td>
                        <td>{{ $institute_name }}</td>
                    </tr>
                    <tr>
                        <td>Applied Trade</td>
                        <td>{{ $trade_name }}</td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>


    <div style="width:100%;height:50px;"></div>

    <div class="footer"style="border:1px solid black;margin-top:30px;">
        <div>
            @if ($payment->trans_id)
            <h4 style="text-align:center;"><u>MONEY RECEIPT</u></h4>

                <p style="padding:5px;">
                    Received <b>Rs.{{ $payment->trans_amount }}/- only</b> through
                    {{ $payment->trans_mode }} on {{ $payment->trans_time }} vide Transaction Number:
                    {{ $payment->trans_id }}, Order No: {{ $payment->order_number }} from
                    <b>{{ $students->student_fullname }}</b>, son/daughter of {{ $students->student_guardian_name }}
                    having Application Form Number: <b>{{ $students->student_form_num }}</b>.
                </p>
               
            @else
            <h4 style="text-align:center;"><u>Application Fees Paid but payment faild</u></h4>
                
            @endif

        </div>
    </div>









</body>

</html>
