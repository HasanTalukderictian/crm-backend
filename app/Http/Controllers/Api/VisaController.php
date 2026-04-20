<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visa;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\SendSMSController;
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
        if (auth()->user()->role === 'admin') {
            $data = Visa::with(['country', 'team'])->latest()->get();
        } else {
            $data = Visa::with(['country', 'team'])
                ->where('user_id', auth()->id())
                ->latest()
                ->get();
        }

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $visa = Visa::with(['country', 'team'])->find($id);

        if (!$visa) {
            return response()->json([
                'status' => false,
                'message' => 'Visa record not found'
            ], 404);
        }

        // ✅ Allow admin OR owner
        if (auth()->user()->role !== 'admin' && $visa->user_id !== auth()->id()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

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
        'country' => 'required|exists:countries,id',
        'salesPerson' => 'required|exists:teams,id',
        'applicantType' => 'required|in:job,business,others',
        'status' => 'nullable|in:Pending,Processing,Complete,Cancle',
        'salaryAmount' => 'nullable|numeric',
        'remainder_days' => 'nullable|integer|min:0',
        'note' => 'nullable|string|max:255',
        'profession_name' => 'nullable|string|max:255',
        'missing_file' => 'nullable|string|max:255',
    ]);

    // ================= Labels =================
    $fieldLabels = [
        'image' => 'Customer Image',
        'bankCertificate' => 'Bank Certificate',
        'nidFile' => 'NID Copy',
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

    $missingFields = [];

    // ================= Applicant Type =================
    if ($request->applicantType === "job") {

        $jobFiles = ['nocLetter','officeId','salarySlips','governmentOrder','visitingCard'];

        foreach ($jobFiles as $file) {
            if (!$request->hasFile($file) && empty($visa->$file)) {
                $missingFields[] = $fieldLabels[$file];
            }
        }

        if (!$request->salaryAmount) {
            $missingFields[] = 'Salary Amount';
        }

    } elseif ($request->applicantType === "business") {

        $businessFiles = ['blankOfficePad','renewalTradeLicense','memorandumLimited'];

        foreach ($businessFiles as $file) {
            if (!$request->hasFile($file) && empty($visa->$file)) {
                $missingFields[] = $fieldLabels[$file];
            }
        }
    }

    // ================= Common =================
    $commonFiles = ['image','bankCertificate','nidFile','assetValuation','birthCertificate','marriageCertificate'];

    foreach ($commonFiles as $field) {
        if ($field === 'assetValuation') {
            if (empty($request->$field) && empty($visa->$field)) {
                $missingFields[] = $fieldLabels[$field];
            }
        } else {
            if (!$request->hasFile($field) && empty($visa->$field)) {
                $missingFields[] = $fieldLabels[$field];
            }
        }
    }

    // ================= FILE CHECKS =================
    $fileChecks = json_decode($request->fileChecks, true) ?? [];




    // ================= MESSAGE GENERATION =================
    $customerName = $request->name;
    $message = "";

    $currentMissing = [];

    if ($request->applicantType === "others") {
        // ✅ checkbox selected files check
        foreach ($fileChecks as $key => $value) {
            if (!empty($value) && isset($fieldLabels[$key])) {
                $currentMissing[] = $fieldLabels[$key];
            }
        }

        // ✅ custom input check
        if (!empty($request->missing_file)) {
            $currentMissing[] = $request->missing_file;
        }
    } else {
        // job অথবা business এর জন্য $missingFields ব্যবহার হবে
        $currentMissing = $missingFields;
    }

    // চূড়ান্ত মেসেজ কন্ডিশন
    if (!empty($currentMissing)) {
        $message = "Dear {$customerName}, your application is incomplete.\n";
        $message .= "Missing files: " . implode(", ", $currentMissing) . ".\n";

        if ($request->applicantType === "others" && !empty($request->profession_name)) {
            $message .= "Profession: " . $request->profession_name . "\n";
        }

        $message .= "Please submit these files to our office as soon as possible.";
    } else {
        $message = "Dear {$customerName}, your application is complete.\n";
        $message .= "Thank you for your cooperation.";
    }



    // ================= UPDATE DATA =================
    $visa->update([
        'name' => $request->name,
        'phone' => $request->phone,
        'member' => $request->member,
        'passport' => $request->passport,
        'invoice' => $request->invoice,
        'applicant_type' => $request->applicantType,
        'country_id' => $request->country,
        'team_id' => $request->salesPerson,
        'date' => $request->date ? Carbon::parse($request->date)->format('Y-m-d') : null,
        'asset_valuation' => $request->assetValuation,
        'salary_amount' => $request->salaryAmount,
        'remainder_days' => $request->remainder_days,
        'note' => $request->note,
        'status' => $request->status ?? $visa->status,
        'profession_name' => $request->profession_name,
        'missing_file' => $request->missing_file,
    ]);

    // ================= FILE UPLOAD =================
    foreach (array_keys($fieldLabels) as $file) {
        if ($request->hasFile($file)) {
            $path = $request->file($file)->store('visa', 'public');
            $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $file));
            $visa->$column = asset('storage/' . $path);
        }
    }

    $visa->save();

    // ================= SMS =================
    try {
        $phone = '88' . $request->phone;
        SendSMSController::sendSms($phone, $message);
    } catch (\Exception $e) {
        Log::error('SMS Failed: ' . $e->getMessage());
    }

    MessageLog::create([
        'visa_id' => $visa->id,
        'phone' => $request->phone,
        'message' => $message,
        'type' => 'sms'
    ]);

    return response()->json([
        'status' => true,
        'message' => $message,
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
            'country' => 'required|exists:countries,id',
            'salesPerson' => 'required|exists:teams,id',
            'applicantType' => 'required|in:job,business,others',
            'remainder_days' => 'required|integer|min:0',
            'note' => 'required|string|max:255',
            'member' => 'required|string',
            'salaryAmount' => 'nullable|numeric',
            'status' => 'nullable|in:Pending,Processing,Complete,Cancle',
            'profession_name' => 'nullable|string|max:255',
            'missing_file' => 'nullable|string|max:255',
        ]);

        // ================= Labels =================
        $fieldLabels = [
            'image' => 'Customer Image',
            'bankCertificate' => 'Bank Certificate',
            'nidFile' => 'NID Copy',
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

        $missingFields = [];

        // ================= Applicant Type Check =================
        if ($request->applicantType === "job") {

            $jobFiles = ['nocLetter', 'officeId', 'salarySlips', 'governmentOrder', 'visitingCard'];

            foreach ($jobFiles as $file) {
                if (!$request->hasFile($file)) {
                    $missingFields[] = $fieldLabels[$file];
                }
            }

            if (!$request->salaryAmount) {
                $missingFields[] = 'Salary Amount';
            }
        } elseif ($request->applicantType === "business") {

            $businessFiles = ['blankOfficePad', 'renewalTradeLicense', 'memorandumLimited'];

            foreach ($businessFiles as $file) {
                if (!$request->hasFile($file)) {
                    $missingFields[] = $fieldLabels[$file];
                }
            }
        }

        // ================= Common Files =================
        $commonFiles = [
            'image',
            'bankCertificate',
            'nidFile',
            'assetValuation',
            'birthCertificate',
            'marriageCertificate'
        ];

        foreach ($commonFiles as $field) {

            if ($field === 'assetValuation') {
                if (empty($request->$field)) {
                    $missingFields[] = $fieldLabels[$field];
                }
            } else {
                if (!$request->hasFile($field)) {
                    $missingFields[] = $fieldLabels[$field];
                }
            }
        }

        // ================= FILE CHECKS =================
        $fileChecks = json_decode($request->fileChecks, true) ?? [];

        // ================= MESSAGE BUILD =================
        $customerName = $request->name;
        $message = "";

        // 🔥 যদি others হয় → custom message only
        if ($request->applicantType === "others") {

            $message = "Dear {$customerName}, your application is incomplete.\n";

            // ✅ checkbox selected files
            $selectedLabels = [];
            foreach ($fileChecks as $key => $value) {
                if (!empty($value) && isset($fieldLabels[$key])) {
                    $selectedLabels[] = $fieldLabels[$key];
                }
            }

            if (!empty($selectedLabels)) {
                $message .= "Selected Missing Files: " . implode(", ", $selectedLabels) . "\n";
            }

            // ✅ custom missing file input
            if (!empty($request->missing_file)) {
                $message .= "Custom Missing File: " . $request->missing_file . "\n";
            }



            $message .= "Please submit these files to our office as soon as possible.";
        } else {

            // 🔥 default logic (job + business)
            if (!empty($missingFields)) {

                $missingList = implode(", ", $missingFields);

                $message = "Dear {$customerName}, your application is incomplete.\n";
                $message .= "Missing files: {$missingList}.\n";
                $message .= "Please submit these files to our office as soon as possible.";
            } else {

                $message = "Dear {$customerName}, your application is not complete.\n";
                $message .= "Thank you for your cooperation.";
            }
        }

        // ================= STORE =================
        $visa = new Visa();

        $this->updateTargetAchieved($visa);

        $visa->user_id = auth()->id();
        $visa->name = $request->name;
        $visa->note = $request->note;
        $visa->phone = $request->phone;
        $visa->passport = $request->passport;
        $visa->invoice = $request->invoice;
        $visa->applicant_type = $request->applicantType;
        $visa->country_id = $request->country;
        $visa->team_id = $request->salesPerson;
        $visa->member = $request->member;
        $visa->remainder_days = $request->remainder_days;

        $visa->date = $request->date
            ? Carbon::parse($request->date)->format('Y-m-d')
            : null;

        $visa->asset_valuation = $request->assetValuation;
        $visa->salary_amount = $request->salaryAmount;
        $visa->status = $request->status ?? 'Pending';

        // ================= OTHERS =================
        if ($request->applicantType === "others") {
            if (!empty($request->profession_name)) {
                $message .= "\nProfession Name: " . $request->profession_name;
            }

            if (!empty($request->missing_file)) {
                $message .= "\nMissing File: " . $request->missing_file;
            }
        }

        // save others fields
        $visa->profession_name = $request->profession_name;
        $visa->missing_file = $request->missing_file;

        // ================= FILE UPLOAD =================
        $files = array_keys($fieldLabels);

        foreach ($files as $file) {
            if ($request->hasFile($file)) {

                $path = $request->file($file)->store('visa', 'public');

                $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $file));

                $visa->$column = asset('storage/' . $path);
            }
        }

        $visa->save();

        // ================= TARGET UPDATE =================
        $target = Target::where('user_id', auth()->id())
            ->where('year', date('Y'))
            ->where('month', date('m'))
            ->first();

        if ($target && $visa->status !== 'Cancle') {

            $memberCount = is_numeric($request->member)
                ? (int) $request->member
                : count(explode(',', $request->member));

            $target->achieved = min($target->achieved + $memberCount, $target->target);
            $target->save();
        }

        // ================= SMS =================
        try {
            $phone = '88' . $request->phone;
            SendSMSController::sendSms($phone, $message);
        } catch (\Exception $e) {
            Log::error('SMS Failed: ' . $e->getMessage());
        }

        MessageLog::create([
            'visa_id' => $visa->id,
            'phone' => $request->phone,
            'message' => $message,
            'type' => 'sms'
        ]);

        return response()->json([
            'status' => true,
            'message' => $message,
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



    function updateTargetAchieved($visa, $oldStatus = null)
    {
        $target = Target::where('user_id', $visa->user_id)
            ->where('year', date('Y', strtotime($visa->date)))
            ->where('month', date('m', strtotime($visa->date)))
            ->first();

        if (!$target) return;

        $memberCount = is_numeric($visa->member)
            ? (int) $visa->member
            : count(explode(',', $visa->member));

        // ================= LOGIC =================

        // 1️⃣ New entry (no old status)
        if ($oldStatus === null && $visa->status !== 'Cancle') {
            $target->achieved += $memberCount;
        }

        // 2️⃣ Status changed to Cancel
        if ($oldStatus !== 'Cancle' && $visa->status === 'Cancle') {
            $target->achieved -= $memberCount;
        }

        // 3️⃣ Cancel → Active again
        if ($oldStatus === 'Cancle' && $visa->status !== 'Cancle') {
            $target->achieved += $memberCount;
        }

        // prevent negative
        if ($target->achieved < 0) {
            $target->achieved = 0;
        }

        $target->save();
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
}
