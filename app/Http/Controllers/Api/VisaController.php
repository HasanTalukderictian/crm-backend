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

class VisaController extends Controller
{




    public function index()
    {
        $query = Visa::with(['team'])->latest();

        if (auth()->user()->role !== 'admin') {
            $query->where('user_id', auth()->id())
                ->latest()
                ->get();
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

        // ================= AUTH =================
        if (auth()->user()->role !== 'admin' && $visa->user_id !== auth()->id()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // ================= SAFE COUNTRY PARSE =================
        $countryIds = is_string($visa->country_id)
            ? json_decode($visa->country_id, true)
            : ($visa->country_id ?? []);

        if (!is_array($countryIds)) {
            $countryIds = [];
        }

        $countries = \App\Models\Country::whereIn('id', $countryIds)->get();

        // attach
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

        // ================= Validation =================
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|digits:11',
            'member' => 'required|string',
            'passport' => 'required|min:6|max:10',
            'invoice' => 'required|string|max:255|unique:visas,invoice,' . $id,
            'country' => 'required|array',
            'country.*' => 'exists:countries,id',
            'salesPerson' => 'required|exists:teams,id',
            'applicantType' => 'required|in:job,business,others',
            'date' => 'nullable|date',
        ]);

        // ================= FIELD LABELS =================
        $fieldLabels = [
            'image' => 'Customer Image',
            'bankCertificate' => 'Bank Certificate',
            'nidFile' => 'NID Copy',

            // 🔥 ADD THIS
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
        ];

        // ================= FILE CHECK =================
        $fileChecks = json_decode($request->fileChecks, true) ?? [];

        $missingFiles = [];

        foreach ($fileChecks as $key => $value) {
            if (!empty($value) && isset($fieldLabels[$key])) {
                $missingFiles[] = $fieldLabels[$key];
            }
        }

        // optional manual missing text
        if (!empty($request->missing_file)) {
            $missingFiles[] = $request->missing_file;
        }

        // ================= SMS MESSAGE LOGIC =================
        $customerName = $request->name;

        if (count($missingFiles) > 0) {

            // ❌ INCOMPLETE SMS
            $message = "Dear {$customerName}, your application is incomplete.\n";
            $message .= "Missing files: " . implode(", ", $missingFiles) . ".\n";
            $message .= "Please submit them as soon as possible.";
        } else {

            // ✅ COMPLETE SMS
            $message = "Dear {$customerName}, your application is complete. Thank you for your submission.";
        }

        // ================= UPDATE VISA =================
        $visa->update([
            'name' => $request->name,
            'phone' => $request->phone,
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
            'remainder_days' => $request->remainder_days,
            'note' => $request->note,
            'profession_name' => $request->profession_name,
            'missing_file' => $request->missing_file,
        ]);

        // ================= FILE UPLOAD =================
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

        // ================= LOG =================
        MessageLog::create([
            'visa_id' => $visa->id,
            'phone' => $request->phone,
            'message' => $message,
            'type' => 'sms'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Visa updated successfully',
            'data' => $visa
        ]);
    }


       public function store(Request $request)
{
    // ================= Validation =================
    $request->validate([
        'name' => 'required|string|max:255',
        'phone' => 'required|digits:11',
        'passport' => 'required|min:6|max:10',
        'invoice' => 'required|string|max:255|unique:visas,invoice',
        'country' => 'required|array',
        'country.*' => 'exists:countries,id',
        'salesPerson' => 'required|exists:teams,id',
        'applicantType' => 'required|in:job,business,others',
        'remainder_days' => 'required|integer|min:0',

        // ✅ NOTE এখন optional
        'note' => 'nullable|string|max:255',

        'member' => 'required|string',
        'date' => 'nullable|date',
        'status' => 'nullable|in:Pending,Processing,Complete,Cancle',
    ]);

    // ================= Labels Mapping =================
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
    ];

    // ================= Selected Files =================
    $selectedFileNames = [];
    $fileChecks = json_decode($request->fileChecks, true) ?? [];

    foreach ($fileChecks as $key => $isSelected) {
        if ($isSelected && isset($fieldLabels[$key])) {
            $selectedFileNames[] = $fieldLabels[$key];
        }
    }

    // ================= SMS Message =================
    $customerName = $request->name;
    $message = "Dear {$customerName}, your application on going.\n";

    if (!empty($selectedFileNames)) {
        $message .= "Missing Files: " . implode(", ", $selectedFileNames) . ".\n";
    }

    if (!empty($request->missing_file)) {
        $message .= "Additional Info: " . $request->missing_file . "\n";
    }

    $message .= "Thank you for being with us.";

    // ================= Store =================
    $visa = new Visa();

    $visa->user_id = auth()->id();
    $visa->name = $request->name;
    $visa->phone = $request->phone;
    $visa->passport = $request->passport;
    $visa->invoice = $request->invoice;
    $visa->applicant_type = $request->applicantType;

    // ✅ FIX: array → json
    $visa->country_id = json_encode($request->country);

    $visa->team_id = $request->salesPerson;
    $visa->member = $request->member;
    $visa->remainder_days = $request->remainder_days;

    // ✅ note optional
    $visa->note = $request->note ?? null;

    $visa->date = $request->date
        ? Carbon::parse($request->date)->format('Y-m-d')
        : null;

    $visa->salary_amount = $request->salaryAmount;
    $visa->status = $request->status ?? 'Pending';
    $visa->profession_name = $request->profession_name;
    $visa->missing_file = $request->missing_file;

    // ================= File Upload =================
    foreach ($fieldLabels as $file => $label) {
        if ($request->hasFile($file)) {
            $path = $request->file($file)->store('visa', 'public');
            $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $file));
            $visa->$column = asset('storage/' . $path);
        }
    }

    $visa->save();

    // ================= Target Update =================
    $this->updateTargetAchieved($visa);

    // ================= Send SMS =================
    try {
        $phone = '88' . $request->phone;
        SendSMSController::sendSms($phone, $message);
    } catch (\Exception $e) {
        Log::error('SMS Failed: ' . $e->getMessage());
    }

    // ================= Log =================
    MessageLog::create([
        'visa_id' => $visa->id,
        'phone' => $request->phone,
        'message' => $message,
        'type' => 'sms'
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Application stored and SMS sent.',
        'data' => $visa
    ]);
}
    public function destroy($id)
    {
        $visa = Visa::findOrFail($id);

        // 🔥 member count বের করা (delete করার আগে)
        $memberCount = is_numeric($visa->member)
            ? (int) $visa->member
            : count(explode(',', $visa->member));

        // 🔥 delete
        $visa->delete();

        // 🔥 target update
        $target = Target::where('user_id', $visa->user_id)
            ->where('year', date('Y'))
            ->where('month', date('m'))
            ->first();

        if ($target) {

            $newAchieved = $target->achieved - $memberCount;

            // ❌ negative না হয়
            $target->achieved = $newAchieved < 0 ? 0 : $newAchieved;

            $target->save();

            $oldStatus = $visa->status;
            $this->updateTargetAchieved($visa, $oldStatus);
        }

        return response()->json([
            'status' => true,
            'message' => ' Visa Delete  Successfully'
        ]);
    }



    function updateTargetAchieved($visa, $oldStatus = null, $oldMember = null, $oldDate = null)
    {
        $newDate = $visa->date;
        $newMonth = date('m', strtotime($newDate));
        $newYear = date('Y', strtotime($newDate));

        $newMemberCount = is_numeric($visa->member)
            ? (int) $visa->member
            : count(explode(',', $visa->member));

        // 🔥 OLD DATA
        if ($oldDate) {
            $oldMonth = date('m', strtotime($oldDate));
            $oldYear = date('Y', strtotime($oldDate));
        }

        $oldMemberCount = is_numeric($oldMember)
            ? (int) $oldMember
            : count(explode(',', $oldMember));

        // ================= OLD TARGET (remove) =================
        if ($oldStatus !== null && $oldStatus !== 'Cancle') {

            $oldTarget = Target::where('user_id', $visa->user_id)
                ->where('year', $oldYear)
                ->where('month', $oldMonth)
                ->first();

            if ($oldTarget) {
                $oldTarget->achieved -= $oldMemberCount;

                if ($oldTarget->achieved < 0) {
                    $oldTarget->achieved = 0;
                }

                $oldTarget->save();
            }
        }

        // ================= NEW TARGET (add) =================
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

        // 🔐 user role check
        if (auth()->user()->role !== 'admin') {
            $query->where('user_id', auth()->id());
        }

        $data = $query
            ->select(
                DB::raw("MONTH(date) as month"),
                DB::raw("COUNT(*) as total")
            )
            ->whereYear('date', date('Y')) // current year
            ->groupBy(DB::raw("MONTH(date)"))
            ->orderBy(DB::raw("MONTH(date)"))
            ->get();

        // 🟢 12 months default (0 fill)
        $months = [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 0,
            8 => 0,
            9 => 0,
            10 => 0,
            11 => 0,
            12 => 0
        ];

        foreach ($data as $item) {
            $months[$item->month] = $item->total;
        }

        return response()->json([
            'status' => true,
            'data' => array_values($months) // [Jan, Feb, Mar...]
        ]);
    }




    public function monthlyVisaStatusSummary()
    {
        $query = Visa::query();

        // 🔐 Role check
        if (auth()->user()->role !== 'admin') {
            $query->where('user_id', auth()->id());
        }

        // 📅 Current month + year filter
        $query->whereYear('date', date('Y'))
            ->whereMonth('date', date('m'));

        // 📊 Status wise count
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
    // 1. Check if user is logged in
    $user = auth()->user();
    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthenticated. Please login first.'
        ], 401);
    }

    // 2. Fetch Visa Record
    $visa = Visa::with(['team', 'user'])->where('invoice', $invoice)->first();

    if (!$visa) {
        return response()->json([
            'status' => false,
            'message' => 'Invoice not found'
        ], 404);
    }

    // 3. Secure Role Check
    // Ekhon ar error dibe na karon upore check kora hoyeche user ache kina
    if ($user->role !== 'admin' && $visa->user_id !== $user->id) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthorized access to this invoice'
        ], 403);
    }

    // ... baki parsing code (country decoding etc.)
    $countryIds = is_string($visa->country_id) ? json_decode($visa->country_id, true) : ($visa->country_id ?? []);
    $countries = \App\Models\Country::whereIn('id', $countryIds)->pluck('name')->toArray();

    return response()->json([
        'status' => true,
        'data' => [
            'customerName'   => $visa->name,
            'customerPhone'  => $visa->phone,
            'appliedCountry' => implode(", ", $countries),
            'salesPerson'    => $visa->team->name ?? 'N/A',
            'usersName'      => $visa->user->name ?? 'N/A',
            'invoice'        => $visa->invoice,
        ]
    ]);
}
}
