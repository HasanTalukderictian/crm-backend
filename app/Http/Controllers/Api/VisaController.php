<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visa;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\SendSMSController;
use App\Models\MessageLog;
use App\Models\Target;
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

        if (!auth()->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        if (auth()->user()->role !== 'admin' && $visa->user_id !== auth()->id()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // ================= Validation =================
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|digits:11',
            'member' => 'required|string',
            'passport' => 'required|min:6|max:9',
            'invoice' => 'required|string|max:255|unique:visas,invoice,' . $id,
            'country' => 'required|exists:countries,id',
            'salesPerson' => 'required|exists:teams,id',
            'applicantType' => 'required|in:job,business',
            'status' => 'nullable|in:Pending,Processing,Complete',
            'salaryAmount' => 'nullable|numeric',
            'remainder_days' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        // ================= Update Basic Info =================
        $visa->name = $request->name;
        $visa->phone = $request->phone;
        $visa->member = $request->member;
        $visa->passport = $request->passport;
        $visa->invoice = $request->invoice;
        $visa->applicant_type = $request->applicantType;
        $visa->country_id = $request->country;
        $visa->team_id = $request->salesPerson;
        $visa->date = $request->date;

        $visa->asset_valuation = $request->assetValuation;
        $visa->salary_amount = $request->salaryAmount;
        $visa->remainder_days = $request->remainder_days;
        $visa->note = $request->note;

        $visa->status = $request->status ?? $visa->status;

        // ================= File Upload =================
        $files = [
            'image',
            'bankCertificate',
            'nidFile',
            'birthCertificate',
            'marriageCertificate',
            'nocLetter',
            'officeId',
            'salarySlips',
            'governmentOrder',
            'visitingCard',
            'blankOfficePad',
            'renewalTradeLicense',
            'memorandumLimited'
        ];

        foreach ($files as $file) {

            if ($request->hasFile($file)) {

                $path = $request->file($file)->store('visa', 'public');

                $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $file));

                $visa->$column = asset('storage/' . $path);
            }
        }

        $visa->save();

        // ================= TARGET UPDATE (FIXED) =================
        $target = Target::where('user_id', auth()->id())
            ->where('year', date('Y'))
            ->where('month', date('m'))
            ->first();

        if ($target) {

            $memberCount = is_numeric($request->member)
                ? (int) $request->member
                : count(explode(',', $request->member));

            // ❗ IMPORTANT FIX: update না, শুধু increment করলে duplicate counting হবে
            // better approach: recalculate OR only add difference

            $target->achieved = min($target->achieved + $memberCount, $target->target);
            $target->save();
        }

        // ================= MESSAGE =================
        $message = "Dear {$request->name}, your data has been updated successfully ✅";

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
            'applicantType' => 'required|in:job,business',
            'remainder_days' => 'required|integer|min:0',
            'note' => 'required|string|max:255',
            'member' => 'required|string',
            'salaryAmount' => 'nullable|numeric',
            'status' => 'nullable|in:Pending,Processing,Complete',
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

        // ================= Message =================
        $customerName = $request->name;

        $message = count($missingFields) > 0
            ? "Dear {$customerName}, your application is incomplete. Missing: " . implode(", ", $missingFields)
            : "Dear {$customerName}, your registration is complete ✅";

        // ================= Store =================
        $visa = new Visa();

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
        $visa->date = $request->date;
        $visa->asset_valuation = $request->assetValuation;
        $visa->salary_amount = $request->salaryAmount;
        $visa->status = $request->status ?? 'Pending';

        // ================= File Upload =================
        $files = [
            'image',
            'bankCertificate',
            'nidFile',
            'birthCertificate',
            'marriageCertificate',
            'nocLetter',
            'officeId',
            'salarySlips',
            'governmentOrder',
            'visitingCard',
            'blankOfficePad',
            'renewalTradeLicense',
            'memorandumLimited'
        ];

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

        if ($target) {

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

        // ================= Response =================
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
        }

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully'
        ]);
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
            DB::raw("COUNT(*) as total")
        )->first();

        return response()->json([
            'status' => true,
            'data' => [
                'pending' => $data->pending ?? 0,
                'processing' => $data->processing ?? 0,
                'complete' => $data->complete ?? 0,
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
}
