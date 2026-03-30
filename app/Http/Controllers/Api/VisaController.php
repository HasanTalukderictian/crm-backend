<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visa;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\SendSMSController;
use App\Models\Target;
use Illuminate\Support\Facades\Auth;
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







    // public function update(Request $request, $id)
    // {
    //     $visa = Visa::find($id);

    //     if (!$visa) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Visa record not found'
    //         ], 404);
    //     }

    //     if (!auth()->check()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Unauthenticated'
    //         ], 401);
    //     }

    //     // ✅ Allow admin OR owner
    //     if (auth()->user()->role !== 'admin' && $visa->user_id !== auth()->id()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Unauthorized access'
    //         ], 403);
    //     }

    //     // Validation
    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'phone' => 'required|digits:11',
    //         'member' => 'required|string',
    //         'passport' => 'required|min:6|max:9',
    //         'invoice' => 'required|string|max:255|unique:visas,invoice,' . $id,
    //         'country' => 'required|exists:countries,id',
    //         'salesPerson' => 'required|exists:teams,id',
    //         'applicantType' => 'required|in:job,business',
    //     ]);

    //     // ================= FILE CHECK =================
    //     $missingFields = [];

    //     $fileMap = [
    //         'image' => 'Image',
    //         'bankCertificate' => 'Bank Certificate',
    //         'nidFile' => 'NID File',
    //         'birthCertificate' => 'Birth Certificate',
    //         'marriageCertificate' => 'Marriage Certificate',
    //         'fixedDepositCertificate' => 'Fixed Deposit Certificate',
    //         'taxCertificate' => 'Tax Certificate',
    //         'tinCertificate' => 'TIN Certificate',
    //         'creditCardCopy' => 'Credit Card Copy',
    //         'covidCertificate' => 'Covid Certificate',
    //         'nocLetter' => 'NOC Letter',
    //         'officeId' => 'Office ID',
    //         'salarySlips' => 'Salary Slips',
    //         'governmentOrder' => 'Government Order',
    //         'visitingCard' => 'Visiting Card',
    //         'companyBankStatement' => 'Company Bank Statement',
    //         'blankOfficePad' => 'Blank Office Pad',
    //         'renewalTradeLicense' => 'Renewal Trade License',
    //         'memorandumLimited' => 'Memorandum Limited'
    //     ];

    //     // ================= JOB / BUSINESS CONDITION =================
    //     if ($request->applicantType === "job") {

    //         if (!$request->hasFile('nocLetter') && empty($visa->noc_letter)) $missingFields[] = "NOC Letter";
    //         if (!$request->hasFile('officeId') && empty($visa->office_id)) $missingFields[] = "Office ID";
    //         if (!$request->hasFile('salarySlips') && empty($visa->salary_slips)) $missingFields[] = "Salary Slips";
    //         if (!$request->hasFile('governmentOrder') && empty($visa->government_order)) $missingFields[] = "Government Order";
    //         if (!$request->hasFile('visitingCard') && empty($visa->visiting_card)) $missingFields[] = "Visiting Card";
    //         if (empty($visa->salary_amount) && empty($request->salaryAmount)) $missingFields[] = "Salary Amount";
    //     } elseif ($request->applicantType === "business") {

    //         if (!$request->hasFile('blankOfficePad') && empty($visa->blank_office_pad)) $missingFields[] = "Blank Office Pad";
    //         if (!$request->hasFile('renewalTradeLicense') && empty($visa->renewal_trade_license)) $missingFields[] = "Renewal Trade License";
    //         if (!$request->hasFile('memorandumLimited') && empty($visa->memorandum_limited)) $missingFields[] = "Memorandum Limited";
    //     } else {
    //         // fallback
    //         foreach ($fileMap as $field => $label) {
    //             $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $field));

    //             if (!$request->hasFile($field) && empty($visa->$column)) {
    //                 $missingFields[] = $label;
    //             }
    //         }
    //     }

    //     // ================= MESSAGE =================
    //     $customerName = $request->name;

    //     if (count($missingFields) > 0) {
    //         $message = "Dear {$customerName}, your application is incomplete. Missing: " . implode(", ", $missingFields);
    //     } else {
    //         $message = "Dear {$customerName}, your registration is complete ✅";
    //     }

    //     // ================= UPDATE DATA =================
    //     $visa->name = $request->name;
    //     $visa->phone = $request->phone;
    //     $visa->member = $request->member;
    //     $visa->passport = $request->passport;
    //     $visa->invoice = $request->invoice;
    //     $visa->applicant_type = $request->applicantType;

    //     $visa->country_id = $request->country;
    //     $visa->team_id = $request->salesPerson;

    //     $visa->date = $request->date;
    //     $visa->asset_valuation = $request->assetValuation;
    //     $visa->salary_amount = $request->salaryAmount;

    //     // ================= FILE UPLOAD =================
    //     foreach ($fileMap as $field => $label) {

    //         if ($request->hasFile($field)) {

    //             $uploadedFile = $request->file($field);
    //             $path = $uploadedFile->store('visa', 'public');

    //             $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $field));

    //             $visa->$column = asset('storage/' . $path);
    //         }
    //     }

    //     $visa->save();

    //     // ================= SMS =================
    //     try {
    //         $phone = '88' . $visa->phone;
    //         SendSMSController::sendSms($phone, $message);
    //     } catch (\Exception $e) {
    //         Log::error('SMS Failed: ' . $e->getMessage());
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'message' => $message,
    //         'data' => $visa
    //     ]);
    // }

    //     public function store(Request $request)
    // {
    //     // Validation
    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'phone' => 'required|digits:11',
    //         'passport' => 'required|min:6|max:9',
    //         'invoice' => 'required|string|max:255|unique:visas,invoice',
    //         'country' => 'required|exists:countries,id',
    //         'salesPerson' => 'required|exists:teams,id',
    //         'applicantType' => 'required|in:job,business',
    //         'member' => 'required|string',
    //     ]);

    //     // ================= Missing Field Check =================
    //     $missingFields = [];

    //     if ($request->applicantType === "job") {

    //         if (!$request->hasFile('nocLetter')) $missingFields[] = "NOC Letter";
    //         if (!$request->hasFile('officeId')) $missingFields[] = "Office ID";
    //         if (!$request->hasFile('salarySlips')) $missingFields[] = "Salary Slips";
    //         if (!$request->hasFile('governmentOrder')) $missingFields[] = "Government Order";
    //         if (!$request->hasFile('visitingCard')) $missingFields[] = "Visiting Card";
    //         if (!$request->salaryAmount) $missingFields[] = "Salary Amount";

    //     } elseif ($request->applicantType === "business") {

    //         if (!$request->hasFile('blankOfficePad')) $missingFields[] = "Blank Office Pad";
    //         if (!$request->hasFile('renewalTradeLicense')) $missingFields[] = "Renewal Trade License";
    //         if (!$request->hasFile('memorandumLimited')) $missingFields[] = "Memorandum Limited";
    //     }

    //     // ================= Message =================
    //     if (count($missingFields) > 0) {
    //         $message = "Your application is incomplete. Missing: " . implode(", ", $missingFields);
    //     } else {
    //         $message = "Your registration is complete ✅";
    //     }

    //     // ================= Store Data =================
    //     $visa = new Visa();

    //     // ✅ IMPORTANT: assign logged in user
    //     $visa->user_id = auth()->id();

    //     $visa->name = $request->name;
    //     $visa->phone = $request->phone;
    //     $visa->passport = $request->passport;
    //     $visa->invoice = $request->invoice;
    //     $visa->applicant_type = $request->applicantType;

    //     $visa->country_id = $request->country;
    //     $visa->team_id = $request->salesPerson;
    //     $visa->member = $request->member;

    //     $visa->date = $request->date;
    //     $visa->asset_valuation = $request->assetValuation;
    //     $visa->salary_amount = $request->salaryAmount;

    //     // ================= File Upload =================
    //     $files = [
    //         'image',
    //         'bankCertificate',
    //         'nidFile',
    //         'birthCertificate',
    //         'marriageCertificate',
    //         'fixedDepositCertificate',
    //         'taxCertificate',
    //         'tinCertificate',
    //         'creditCardCopy',
    //         'covidCertificate',
    //         'nocLetter',
    //         'officeId',
    //         'salarySlips',
    //         'governmentOrder',
    //         'visitingCard',
    //         'companyBankStatement',
    //         'blankOfficePad',
    //         'renewalTradeLicense',
    //         'memorandumLimited'
    //     ];

    //     foreach ($files as $file) {
    //         if ($request->hasFile($file)) {

    //             $uploadedFile = $request->file($file);
    //             $path = $uploadedFile->store('visa', 'public');

    //             $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $file));

    //             $visa->$column = asset('storage/' . $path);
    //         }
    //     }

    //     $visa->save();

    //     // ================= SMS SEND =================
    //     try {
    //         $phone = '88' . $request->phone;
    //         SendSMSController::sendSms($phone, $message);
    //     } catch (\Exception $e) {
    //         Log::error('SMS Failed: ' . $e->getMessage());
    //     }

    //     // ================= Response =================
    //     return response()->json([
    //         'status' => true,
    //         'message' => $message,
    //         'data' => $visa
    //     ]);
    // }





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

        // ✅ Allow admin OR owner
        if (auth()->user()->role !== 'admin' && $visa->user_id !== auth()->id()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Validation
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|digits:11',
            'member' => 'required|string',
            'passport' => 'required|min:6|max:9',
            'invoice' => 'required|string|max:255|unique:visas,invoice,' . $id,
            'country' => 'required|exists:countries,id',
            'salesPerson' => 'required|exists:teams,id',
            'applicantType' => 'required|in:job,business',
        ]);

        // ================= FILE LABELS =================
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
            'memorandumLimited' => 'Memorandum Limited'
        ];

        $missingFields = [];

        // ================= COMMON FILE CHECK =================
        $commonFiles = ['image', 'bankCertificate', 'nidFile', 'assetValuation', 'birthCertificate', 'marriageCertificate'];

        foreach ($commonFiles as $field) {

            $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $field));

            if ($field === 'assetValuation') {
                if (empty($request->assetValuation) && empty($visa->asset_valuation)) {
                    $missingFields[] = $fieldLabels[$field];
                }
            } else {
                if (!$request->hasFile($field) && empty($visa->$column)) {
                    $missingFields[] = $fieldLabels[$field];
                }
            }
        }

        // ================= JOB / BUSINESS CONDITION =================
        if ($request->applicantType === "job") {

            if (!$request->hasFile('nocLetter') && empty($visa->noc_letter)) $missingFields[] = $fieldLabels['nocLetter'];
            if (!$request->hasFile('officeId') && empty($visa->office_id)) $missingFields[] = $fieldLabels['officeId'];
            if (!$request->hasFile('salarySlips') && empty($visa->salary_slips)) $missingFields[] = $fieldLabels['salarySlips'];
            if (!$request->hasFile('governmentOrder') && empty($visa->government_order)) $missingFields[] = $fieldLabels['governmentOrder'];
            if (!$request->hasFile('visitingCard') && empty($visa->visiting_card)) $missingFields[] = $fieldLabels['visitingCard'];

            if (empty($visa->salary_amount) && empty($request->salaryAmount)) {
                $missingFields[] = 'Salary Amount';
            }
        } elseif ($request->applicantType === "business") {

            if (!$request->hasFile('blankOfficePad') && empty($visa->blank_office_pad)) $missingFields[] = $fieldLabels['blankOfficePad'];
            if (!$request->hasFile('renewalTradeLicense') && empty($visa->renewal_trade_license)) $missingFields[] = $fieldLabels['renewalTradeLicense'];
            if (!$request->hasFile('memorandumLimited') && empty($visa->memorandum_limited)) $missingFields[] = $fieldLabels['memorandumLimited'];
        }

        // ================= MESSAGE =================
        $customerName = $request->name;

        if (count($missingFields) > 0) {
            $message = "Dear {$customerName}, your application is incomplete. Missing: " . implode(", ", $missingFields);
        } else {
            $message = "Dear {$customerName}, your registration is complete ✅";
        }

        // ================= UPDATE DATA =================
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

        // ================= FILE UPLOAD =================
        foreach ($fieldLabels as $field => $label) {

            if ($request->hasFile($field)) {

                $uploadedFile = $request->file($field);
                $path = $uploadedFile->store('visa', 'public');

                $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $field));

                $visa->$column = asset('storage/' . $path);
            }
        }

        $visa->save();

          $target = Target::where('user_id', auth()->id())
            ->where('year', date('Y'))
            ->where('month', date('m'))
            ->first();

        if ($target) {

            $memberCount = is_numeric($request->member)
                ? (int) $request->member
                : count(explode(',', $request->member));

            $newAchieved = $target->achieved + $memberCount;

            // 🔥 target limit cross na kore
            if ($newAchieved > $target->target) {
                $target->achieved = $target->target;
            } else {
                $target->achieved = $newAchieved;
            }

            $target->save();
        }



        // ================= SMS =================
        try {
            $phone = '88' . $visa->phone;
            SendSMSController::sendSms($phone, $message);
        } catch (\Exception $e) {
            Log::error('SMS Failed: ' . $e->getMessage());
        }

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
            'passport' => 'required|min:6|max:9',
            'invoice' => 'required|string|max:255|unique:visas,invoice',
            'country' => 'required|exists:countries,id',
            'salesPerson' => 'required|exists:teams,id',
            'applicantType' => 'required|in:job,business',
            'member' => 'required|string',
        ]);

        // ================= Missing Field Labels =================
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

            if (!$request->hasFile('nocLetter')) $missingFields[] = $fieldLabels['nocLetter'];
            if (!$request->hasFile('officeId')) $missingFields[] = $fieldLabels['officeId'];
            if (!$request->hasFile('salarySlips')) $missingFields[] = $fieldLabels['salarySlips'];
            if (!$request->hasFile('governmentOrder')) $missingFields[] = $fieldLabels['governmentOrder'];
            if (!$request->hasFile('visitingCard')) $missingFields[] = $fieldLabels['visitingCard'];

            if (!$request->salaryAmount) $missingFields[] = 'Salary Amount';
        } elseif ($request->applicantType === "business") {

            if (!$request->hasFile('blankOfficePad')) $missingFields[] = $fieldLabels['blankOfficePad'];
            if (!$request->hasFile('renewalTradeLicense')) $missingFields[] = $fieldLabels['renewalTradeLicense'];
            if (!$request->hasFile('memorandumLimited')) $missingFields[] = $fieldLabels['memorandumLimited'];
        }

        // ================= Common Required Files =================
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
                if (empty($request->assetValuation)) {
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

        if (count($missingFields) > 0) {
            $message = "Dear {$customerName}, your application is incomplete. Missing: " . implode(", ", $missingFields);
        } else {
            $message = "Dear {$customerName}, your registration is complete ✅";
        }

        // ================= Store Data =================
        $visa = new Visa();

        $visa->user_id = auth()->id();

        $visa->name = $request->name;
        $visa->phone = $request->phone;
        $visa->passport = $request->passport;
        $visa->invoice = $request->invoice;
        $visa->applicant_type = $request->applicantType;

        $visa->country_id = $request->country;
        $visa->team_id = $request->salesPerson;
        $visa->member = $request->member;

        $visa->date = $request->date;
        $visa->asset_valuation = $request->assetValuation;
        $visa->salary_amount = $request->salaryAmount;

        // ================= File Upload =================
        $files = [
            'image',
            'bankCertificate',
            'nidFile',
            'birthCertificate',
            'marriageCertificate',
            'fixedDepositCertificate',
            'taxCertificate',
            'tinCertificate',
            'creditCardCopy',
            'covidCertificate',
            'nocLetter',
            'officeId',
            'salarySlips',
            'governmentOrder',
            'visitingCard',
            'companyBankStatement',
            'blankOfficePad',
            'renewalTradeLicense',
            'memorandumLimited'
        ];

        foreach ($files as $file) {
            if ($request->hasFile($file)) {

                $uploadedFile = $request->file($file);
                $path = $uploadedFile->store('visa', 'public');

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

            $newAchieved = $target->achieved + $memberCount;

            // 🔥 target limit cross na kore
            if ($newAchieved > $target->target) {
                $target->achieved = $target->target;
            } else {
                $target->achieved = $newAchieved;
            }

            $target->save();
        }

        // ================= SMS SEND =================
        try {
            $phone = '88' . $request->phone;
            SendSMSController::sendSms($phone, $message);
        } catch (\Exception $e) {
            Log::error('SMS Failed: ' . $e->getMessage());
        }

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
}
