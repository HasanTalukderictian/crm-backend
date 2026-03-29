<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visa;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\SendSMSController;
use Illuminate\Support\Facades\Log;

class VisaController extends Controller
{

    public function index()
    {
        $data = Visa::with(['country', 'team'])->latest()->get();

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

    // Update basic fields
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

    // File Fields
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

    // ================= SMS MESSAGE =================

    $message = "Dear {$visa->name}, your visa information has been updated successfully.";

    // ================= SMS SEND =================

    try {
        $phone = '88' . $visa->phone; // correct format

        SendSMSController::sendSms($phone, $message);

    } catch (\Exception $e) {
        Log::error('SMS Failed: ' . $e->getMessage());
    }

    // ================= RESPONSE =================

    return response()->json([
        'status' => true,
        'message' => 'Visa Updated Successfully',
        'data' => $visa
    ]);
}



    public function store(Request $request)
    {
        // Validation
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

        // ================= Missing Field Check =================

        $missingFields = [];

        if ($request->applicantType === "job") {

            if (!$request->hasFile('nocLetter')) $missingFields[] = "NOC Letter";
            if (!$request->hasFile('officeId')) $missingFields[] = "Office ID";
            if (!$request->hasFile('salarySlips')) $missingFields[] = "Salary Slips";
            if (!$request->hasFile('governmentOrder')) $missingFields[] = "Government Order";
            if (!$request->hasFile('visitingCard')) $missingFields[] = "Visiting Card";
            if (!$request->salaryAmount) $missingFields[] = "Salary Amount";
        } elseif ($request->applicantType === "business") {

            if (!$request->hasFile('blankOfficePad')) $missingFields[] = "Blank Office Pad";
            if (!$request->hasFile('renewalTradeLicense')) $missingFields[] = "Renewal Trade License";
            if (!$request->hasFile('memorandumLimited')) $missingFields[] = "Memorandum Limited";
        }

        // ================= Message Generate =================

        if (count($missingFields) > 0) {
            $message = "Your application is incomplete. Missing: " . implode(", ", $missingFields);
        } else {
            $message = "Your registration is complete ✅";
        }

        // ================= Store Data =================

        $visa = new Visa();

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

        // ================= SMS SEND (Safe) =================

        try {
            $phone = '88' . $request->phone; // SSL Wireless এ + লাগে না

            SendSMSController::sendSms($phone, $message);
        } catch (\Exception $e) {
            Log::error('SMS Failed: ' . $e->getMessage());
        }

        // ================= Final Response =================

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $visa
        ]);
    }

    public function destroy($id)
    {

        $visa = Visa::findOrFail($id);

        $visa->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully'
        ]);
    }
}
