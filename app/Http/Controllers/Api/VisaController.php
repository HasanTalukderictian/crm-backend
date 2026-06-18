<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visa;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\SendSMSController;
use App\Models\Country;
use App\Models\MessageLog;
use App\Models\Target;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VisaController extends Controller
{

    public function index()
    {
        $query = Visa::with([
            'team',
            'user:id,name'
        ])->latest();

        if (auth()->user()->role !== 'admin') {
            $query->where('user_id', auth()->id());
        }

        $data = $query->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $visa = Visa::with(['team'])->find($id);

        if (!$visa) {
            return response()->json([
                'status' => false,
                'message' => 'Visa record not found'
            ], 404);
        }

        if (auth()->user()->role !== 'admin' && $visa->user_id !== auth()->id()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $countryIds = is_string($visa->country_id)
            ? json_decode($visa->country_id, true)
            : ($visa->country_id ?? []);

        if (!is_array($countryIds)) {
            $countryIds = [];
        }

        $countries = \App\Models\Country::whereIn('id', $countryIds)->get();
        $visa->countries = $countries;

        return response()->json([
            'status' => true,
            'data' => $visa
        ]);
    }

    public function update(Request $request, $id)
    {
        $visa = Visa::find($id);

        if (!$visa) {
            return response()->json([
                'status' => false,
                'message' => 'Visa record not found'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|digits:11',
            'email' => 'required|email|max:255',
            'member' => 'required|string',
            'passport' => 'required|min:6|max:10',
            'invoice' => 'required|string|max:255|unique:visas,invoice,' . $id,
            'country' => 'required|array',
            'country.*' => 'exists:countries,id',
            'salesPerson' => 'required|exists:teams,id',
            'applicantType' => 'required|in:job,business,doctor,lawyer,student,others',
            'date' => 'nullable|date',
            'notaryStatus' => 'nullable|in:Pending,Processing,Missing,No Need',
        ]);

        $fieldLabels = [
            'image' => 'Customer Image',
            'bankCertificate' => 'Bank Certificate',
            'nidFile' => 'NID Copy',
            'fatherNid' => 'Father NID',
            'motherNid' => 'Mother NID',
            'assetValuation' => 'Asset Valuation',
            'birthCertificate' => 'Birth Certificate',
            'marriageCertificate' => 'Marriage Certificate',
            'nocLetter' => 'NOC Letter',
            'officeId' => 'Office ID',
            'salarySlips' => 'Salary Slips',
            'governmentOrder' => 'Government Order',
            'visitingCard' => 'Visiting Card',
            'blankOfficePad' => 'Blank Office Pad',
            'renewalTradeLicense' => 'Renewal Trade License',
            'memorandumLimited' => 'Memorandum Limited',
            'bmdcCertificate' => 'BMDC Certificate',
            'retirementCertificate' => 'Retirement Certificate',
            'barCouncilCertificate' => 'Bar Council Certificate',
            'studentId' => 'Student ID',
            'recommendationLetter' => 'Recommendation Letter',
            'parentOfficeId' => 'Parent Office ID / Trade License',
            'consentLetter' => 'Consent Letter',
            'hotelBooking' => 'Hotel Booking Copy',
            'airTicket' => 'Air Ticket',
            'proofOfResidency' => 'Proof of Residency',
        ];

        $fileChecks = json_decode($request->fileChecks, true) ?? [];
        $missingFiles = [];

        foreach ($fileChecks as $key => $value) {
            if (!empty($value) && isset($fieldLabels[$key])) {
                $missingFiles[] = $fieldLabels[$key];
            }
        }

        if (!empty($request->missing_file)) {
            $missingFiles[] = $request->missing_file;
        }

        $customerName = $request->name;

        if (count($missingFiles) > 0) {
            $message = "Dear {$customerName}, your application is incomplete.\n";
            $message .= "Missing files: " . implode(", ", $missingFiles) . ".\n";
            $message .= "Please submit them as soon as possible.";
        } else {
            $message = "Dear {$customerName}, your application is complete. Thank you for your submission.";
        }

        $visa->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'member' => $request->member,
            'passport' => $request->passport,
            'invoice' => $request->invoice,
            'applicant_type' => $request->applicantType,
            'country_id' => json_encode($request->country),
            'team_id' => $request->salesPerson,
            'status' => $request->status ?? $visa->status,
            'date' => $request->date
                ? Carbon::parse($request->date)->format('Y-m-d')
                : null,
            'salary_amount' => $request->salaryAmount,
            'asset_valuation' => $request->assetValuation ?? 0,
            'remainder_days' => $request->remainder_days,
            'note' => $request->note,
            'profession_name' => $request->profession_name,
            'missing_file' => $request->missing_file,
            'notary_status' => $request->notaryStatus ?? $visa->notary_status,
        ]);

        foreach ($fieldLabels as $file => $label) {
            if ($request->hasFile($file)) {
                $path = $request->file($file)->store('visa', 'public');
                $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $file));
                $visa->$column = asset('storage/' . $path);
            }
        }

        $visa->save();
        $this->updateTargetAchieved($visa);

        // ================= SMS SEND =================
        try {
            $phone = '88' . $request->phone;
            SendSMSController::sendSms($phone, $message);
        } catch (\Exception $e) {
            Log::error('SMS Failed: ' . $e->getMessage());
        }

        // ================= EMAIL SEND =================
        try {
            $this->sendEmail($request->email, $customerName, $message);
        } catch (\Exception $e) {
            Log::error('Email Failed: ' . $e->getMessage());
        }

        // ================= LOG =================
        MessageLog::create([
            'visa_id' => $visa->id,
            'phone' => $request->phone,
            'email' => $request->email,
            'message' => $message,
            'type' => 'both'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Visa updated successfully',
            'data' => $visa
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|digits:11',
            'email' => 'required|email|max:255',
            'passport' => 'required|min:6|max:10',
            'invoice' => 'required|string|max:255|unique:visas,invoice',
            'country' => 'required|array',
            'country.*' => 'exists:countries,id',
            'salesPerson' => 'required|exists:teams,id',
            'applicantType' => 'required|in:job,business,doctor,lawyer,student,others',
            'remainder_days' => 'required|integer|min:0',
            'note' => 'nullable|string|max:255',
            'member' => 'required|string',
            'date' => 'nullable|date',
            'status' => 'nullable|in:Pending,Processing,Complete,Cancle',
            'notaryStatus' => 'nullable|in:Pending,Processing,Missing,No Need',
        ]);

        $fieldLabels = [
            'image' => 'Customer Image',
            'bankCertificate' => 'Bank Certificate',
            'nidFile' => 'NID Copy',
            'fatherNid' => 'Father NID',
            'motherNid' => 'Mother NID',
            'assetValuation' => 'Asset Valuation',
            'birthCertificate' => 'Birth Certificate',
            'marriageCertificate' => 'Marriage Certificate',
            'nocLetter' => 'NOC Letter',
            'officeId' => 'Office ID',
            'salarySlips' => 'Salary Slips',
            'governmentOrder' => 'Government Order',
            'visitingCard' => 'Visiting Card',
            'blankOfficePad' => 'Blank Office Pad',
            'renewalTradeLicense' => 'Renewal Trade License',
            'memorandumLimited' => 'Memorandum Limited',
            'bmdcCertificate' => 'BMDC Certificate',
            'retirementCertificate' => 'Retirement Certificate',
            'barCouncilCertificate' => 'Bar Council Certificate',
            'studentId' => 'Student ID',
            'recommendationLetter' => 'Recommendation Letter',
            'parentOfficeId' => 'Parent Office ID / Trade License',
            'consentLetter' => 'Consent Letter',
            'hotelBooking' => 'Hotel Booking Copy',
            'airTicket' => 'Air Ticket',
            'proofOfResidency' => 'Proof of Residency',
        ];

        $selectedFileNames = [];
        $fileChecks = json_decode($request->fileChecks, true) ?? [];

        foreach ($fileChecks as $key => $isSelected) {
            if ($isSelected && isset($fieldLabels[$key])) {
                $selectedFileNames[] = $fieldLabels[$key];
            }
        }

        $customerName = $request->name;
        $message = "Dear {$customerName}, your application on going.\n";

        if (!empty($selectedFileNames)) {
            $message .= "Missing Files: " . implode(", ", $selectedFileNames) . ".\n";
        }

        if (!empty($request->missing_file)) {
            $message .= "Additional Info: " . $request->missing_file . "\n";
        }

        $message .= "Thank you for being with us.";

        $visa = new Visa();
        $visa->user_id = auth()->id();
        $visa->name = $request->name;
        $visa->phone = $request->phone;
        $visa->email = $request->email;
        $visa->passport = $request->passport;
        $visa->invoice = $request->invoice;
        $visa->applicant_type = $request->applicantType;
        $visa->country_id = json_encode($request->country);
        $visa->team_id = $request->salesPerson;
        $visa->member = $request->member;
        $visa->remainder_days = $request->remainder_days;
        $visa->note = $request->note ?? null;
        $visa->date = $request->date ? Carbon::parse($request->date)->format('Y-m-d') : null;
        $visa->salary_amount = $request->salaryAmount;
        $visa->asset_valuation = $request->assetValuation ?? 0;
        $visa->status = $request->status ?? 'Pending';
        $visa->profession_name = $request->profession_name;
        $visa->missing_file = $request->missing_file;
        $visa->notary_status = $request->notaryStatus ?? null;

        foreach ($fieldLabels as $file => $label) {
            if ($request->hasFile($file)) {
                $path = $request->file($file)->store('visa', 'public');
                $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $file));
                $visa->$column = asset('storage/' . $path);
            }
        }

        $visa->save();
        $this->updateTargetAchieved($visa);

        // ================= SMS SEND =================
        try {
            $phone = '88' . $request->phone;
            SendSMSController::sendSms($phone, $message);
        } catch (\Exception $e) {
            Log::error('SMS Failed: ' . $e->getMessage());
        }

        // ================= EMAIL SEND =================
        try {
            $this->sendEmail($request->email, $customerName, $message);
        } catch (\Exception $e) {
            Log::error('Email Failed: ' . $e->getMessage());
        }

        // ================= LOG =================
        MessageLog::create([
            'visa_id' => $visa->id,
            'phone' => $request->phone,
            'email' => $request->email,
            'message' => $message,
            'type' => 'both'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Application stored and SMS & Email sent.',
            'data' => $visa
        ]);
    }

    // ================= EMAIL SEND FUNCTION =================
    private function sendEmail($email, $customerName, $message)
{
    // কোম্পানির তথ্য (আপনার মতো করে পরিবর্তন করুন)
    $companyName = "Akashbari Holidays";
    $companyLogo = "https://i.ibb.co.com/LDtHPM2s/l-OGO4.webp"; // আপনার লোগো URL
    $websiteLink = "https://www.akashbariholidays.com/";
    $hotlineNumber = "+88009613651900";
    $supportEmail = "akashbariholidays@gmail.com";
    $currentYear = date('Y');

    $subject = "Visa Application Status - " . $customerName;

    $emailBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Visa Application Status</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                line-height: 1.7;
                background-color: #f4f6f9;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: #ffffff;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #1a2a6c, #2d4373);
                padding: 10px 15px 15px;
                text-align: center;
                border-bottom: 4px solid #f39c12;
            }
            .header-logo {
                max-width: 120px;
                height: auto;
                margin-bottom: 10px;
                border-radius: 8px;
                background: white;
                padding: 8px;
            }
            .header-title {
                color: #ffffff;
                font-size: 22px;
                font-weight: 600;
                margin: 10px 0 5px;
                letter-spacing: 0.5px;
            }
            .header-subtitle {
                color: #ecf0f1;
                font-size: 14px;
                font-weight: 300;
                opacity: 0.9;
            }
            .content {
                padding: 30px 35px;
                background: #ffffff;
            }
            .greeting {
                font-size: 18px;
                color: #2c3e50;
                margin-bottom: 15px;
                font-weight: 600;
            }
            .message-box {
                background: #f8f9fa;
                border-left: 4px solid #2d4373;
                padding: 15px 20px;
                margin: 15px 0 20px;
                border-radius: 4px;
            }
            .message-box p {
                margin: 5px 0;
                color: #34495e;
            }
            .divider {
                border: none;
                border-top: 2px dashed #e0e6ed;
                margin: 25px 0;
            }
            .company-info {
                background: #f8f9fa;
                padding: 15px 20px;
                border-radius: 8px;
                margin: 15px 0;
            }
            .company-info h4 {
                color: #2c3e50;
                font-size: 15px;
                margin-bottom: 10px;
            }
            .company-info .info-item {
                display: flex;
                align-items: center;
                margin: 6px 0;
                font-size: 14px;
                color: #555;
            }
            .company-info .info-item span {
                margin-right: 10px;
                font-size: 18px;
            }
            .company-info .info-item a {
                color: #2d4373;
                text-decoration: none;
                font-weight: 500;
            }
            .company-info .info-item a:hover {
                text-decoration: underline;
            }
            .footer {
                background: #2c3e50;
                color: #ecf0f1;
                padding: 20px 30px;
                text-align: center;
                font-size: 13px;
            }
            .footer-links {
                margin-bottom: 10px;
            }
            .footer-links a {
                color: #f39c12;
                text-decoration: none;
                margin: 0 10px;
                font-weight: 500;
            }
            .footer-links a:hover {
                text-decoration: underline;
            }
            .footer-text {
                opacity: 0.8;
                font-size: 12px;
                line-height: 1.6;
            }
            .social-icons {
                margin: 10px 0;
            }
            .social-icons a {
                color: #ecf0f1;
                margin: 0 8px;
                text-decoration: none;
                font-size: 18px;
            }
            .status-badge {
                display: inline-block;
                padding: 4px 16px;
                background: #f39c12;
                color: #fff;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 600;
                margin: 5px 0;
            }
            @media only screen and (max-width: 480px) {
                .container {
                    border-radius: 0;
                }
                .content {
                    padding: 20px;
                }
                .header {
                    padding: 20px 15px;
                }
                .header-title {
                    font-size: 18px;
                }
                .company-info .info-item {
                    font-size: 13px;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <!-- Header with Logo -->
            <div class='header'>
                <img src='{$companyLogo}' alt='{$companyName}' class='header-logo' />
                <div class='header-title'>Visa Application Status</div>

            </div>

            <!-- Content -->
            <div class='content'>

                <div class='message-box'>
                    " . nl2br($message) . "
                </div>

                <hr class='divider'>

                <!-- Company Information -->
                <div class='company-info'>
                    <h4>📌 Need Help? Contact Us</h4>
                    <div class='info-item'>
                        <span>🌐</span>
                        <a href='{$websiteLink}' target='_blank'>{$websiteLink}</a>
                    </div>
                    <div class='info-item'>
                        <span>📞</span>
                        <strong>Hotline:</strong> <a href='tel:{$hotlineNumber}'>{$hotlineNumber}</a>
                    </div>
                    <div class='info-item'>
                        <span>✉️</span>
                        <a href='mailto:{$supportEmail}'>{$supportEmail}</a>
                    </div>
                </div>

                <hr class='divider'>

                <div style='text-align: center; font-size: 14px; color: #7f8c8d;'>
                    <p style='margin: 0;'>Thank you for choosing <strong>{$companyName}</strong></p>
                    <p style='margin: 5px 0 0; font-size: 13px;'>We value your trust and are committed to serving you.</p>
                </div>
            </div>

            <!-- Footer -->
            <div class='footer'>
                <div class='footer-links'>
                    <a href='{$websiteLink}'>Home</a>
                    <span style='color: #7f8c8d;'>|</span>
                    <a href='{$websiteLink}/contact'>Contact Us</a>
                    <span style='color: #7f8c8d;'>|</span>
                    <a href='{$websiteLink}/privacy'>Privacy Policy</a>
                </div>

                <div class='footer-text'>
                    © {$currentYear} <strong>{$companyName}</strong>. All rights reserved.<br>
                    <span style='font-size: 11px; opacity: 0.7;'>
                        This is an automated message. Please do not reply to this email.<br>
                        If you have any questions, please contact our support team.
                    </span>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";

    try {
        Mail::send([], [], function ($message) use ($email, $subject, $emailBody) {
            $message->to($email)
                    ->subject($subject)
                    ->html($emailBody);
        });
        Log::info('Email sent successfully to: ' . $email);
    } catch (\Exception $e) {
        Log::error('Email sending failed: ' . $e->getMessage());
        throw $e;
    }
}

    public function destroy($id)
    {
        $visa = Visa::findOrFail($id);
        $memberCount = is_numeric($visa->member) ? (int) $visa->member : count(explode(',', $visa->member));
        $visa->delete();

        $target = Target::where('user_id', $visa->user_id)
            ->where('year', date('Y'))
            ->where('month', date('m'))
            ->first();

        if ($target) {
            $newAchieved = $target->achieved - $memberCount;
            $target->achieved = $newAchieved < 0 ? 0 : $newAchieved;
            $target->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Visa Deleted Successfully'
        ]);
    }

    function updateTargetAchieved($visa, $oldStatus = null, $oldMember = null, $oldDate = null)
    {
        $newDate = $visa->date;
        $newMonth = date('m', strtotime($newDate));
        $newYear = date('Y', strtotime($newDate));

        $newMemberCount = is_numeric($visa->member) ? (int) $visa->member : count(explode(',', $visa->member));

        if ($oldDate) {
            $oldMonth = date('m', strtotime($oldDate));
            $oldYear = date('Y', strtotime($oldDate));
        }

        $oldMemberCount = is_numeric($oldMember) ? (int) $oldMember : count(explode(',', $oldMember));

        if ($oldStatus !== null && $oldStatus !== 'Cancle') {
            $oldTarget = Target::where('user_id', $visa->user_id)
                ->where('year', $oldYear)
                ->where('month', $oldMonth)
                ->first();

            if ($oldTarget) {
                $oldTarget->achieved -= $oldMemberCount;
                if ($oldTarget->achieved < 0) $oldTarget->achieved = 0;
                $oldTarget->save();
            }
        }

        if ($visa->status !== 'Cancle') {
            $newTarget = Target::where('user_id', $visa->user_id)
                ->where('year', $newYear)
                ->where('month', $newMonth)
                ->first();

            if ($newTarget) {
                $newTarget->achieved += $newMemberCount;
                $newTarget->save();
            }
        }
    }

    public function monthlyVisaStats()
    {
        $query = Visa::query();

        if (auth()->user()->role !== 'admin') {
            $query->where('user_id', auth()->id());
        }

        $data = $query
            ->select(
                DB::raw("MONTH(date) as month"),
                DB::raw("COUNT(*) as total")
            )
            ->whereYear('date', date('Y'))
            ->groupBy(DB::raw("MONTH(date)"))
            ->orderBy(DB::raw("MONTH(date)"))
            ->get();

        $months = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0];

        foreach ($data as $item) {
            $months[$item->month] = $item->total;
        }

        return response()->json([
            'status' => true,
            'data' => array_values($months)
        ]);
    }

    public function monthlyVisaStatusSummary()
    {
        $query = Visa::query();

        if (auth()->user()->role !== 'admin') {
            $query->where('user_id', auth()->id());
        }

        $query->whereYear('date', date('Y'))
            ->whereMonth('date', date('m'));

        $data = $query->select(
            DB::raw("SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending"),
            DB::raw("SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) as processing"),
            DB::raw("SUM(CASE WHEN status = 'Complete' THEN 1 ELSE 0 END) as complete"),
            DB::raw("SUM(CASE WHEN status = 'Cancle' THEN 1 ELSE 0 END) as cancle"),
            DB::raw("COUNT(*) as total")
        )->first();

        return response()->json([
            'status' => true,
            'data' => [
                'pending' => $data->pending ?? 0,
                'processing' => $data->processing ?? 0,
                'complete' => $data->complete ?? 0,
                'cancle' => $data->cancle ?? 0,
                'total' => $data->total ?? 0,
            ]
        ]);
    }

    public function messageLogs($visaId)
    {
        $logs = MessageLog::where('visa_id', $visaId)->latest()->get();

        return response()->json([
            'status' => true,
            'data' => $logs
        ]);
    }

    public function topSalesPersons(Request $request)
    {
        $month = $request->month ?? date('m');
        $year = $request->year ?? date('Y');

        $query = Visa::query();

        if (auth()->user()->role !== 'admin') {
            $query->where('user_id', auth()->id());
        }

        $data = $query
            ->select('team_id', DB::raw('COUNT(*) as total_visas'))
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('status', '!=', 'Cancle')
            ->groupBy('team_id')
            ->orderByDesc('total_visas')
            ->limit(5)
            ->get();

        $data->load('team');

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function getByInvoice($invoice)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated. Please login first.'
            ], 401);
        }

        $visa = Visa::with(['team', 'user'])->where('invoice', $invoice)->first();

        if (!$visa) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        if ($user->role !== 'admin' && $visa->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access to this invoice'
            ], 403);
        }

        $countryIds = is_string($visa->country_id) ? json_decode($visa->country_id, true) : ($visa->country_id ?? []);
        $countries = \App\Models\Country::whereIn('id', $countryIds)->pluck('name')->toArray();

        return response()->json([
            'status' => true,
            'data' => [
                'customerName'   => $visa->name,
                'customerPhone'  => $visa->phone,
                'customerEmail'  => $visa->email,
                'appliedCountry' => implode(", ", $countries),
                'salesPerson'    => $visa->team->name ?? 'N/A',
                'usersName'      => $visa->user->name ?? 'N/A',
                'invoice'        => $visa->invoice,
            ]
        ]);
    }
}
