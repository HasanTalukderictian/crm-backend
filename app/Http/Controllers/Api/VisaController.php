<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visa;
use Illuminate\Http\Request;

class VisaController extends Controller
{

    public function index()
    {
        $data = Visa::with(['country','team'])->latest()->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

     public function show($id)
{
    $visa = Visa::with(['country','team'])->find($id);

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
        'passport' => 'required|min:6|max:9',
        'country' => 'required|exists:countries,id',
        'salesPerson' => 'required|exists:teams,id'
    ]);

    // Update basic fields
    $visa->name = $request->name;
    $visa->phone = $request->phone;
    $visa->passport = $request->passport;

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

            // store new file
            $path = $uploadedFile->store('visa', 'public');

            // convert camelCase → snake_case
            $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $file));

            // update column
            $visa->$column = asset('storage/' . $path);
        }
    }

    $visa->save();

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
            'country' => 'required|exists:countries,id',
            'salesPerson' => 'required|exists:teams,id'
        ]);


        $visa = new Visa();

        $visa->name = $request->name;
        $visa->phone = $request->phone;
        $visa->passport = $request->passport;

        // Foreign Keys
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

        // Save file in storage/app/public/visa
        $path = $uploadedFile->store('visa', 'public');

        // convert camelCase → snake_case
        $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $file));

        // Save full URL in DB
        $visa->$column = asset('storage/' . $path);
    }
}

        $visa->save();

        return response()->json([
            'status' => true,
            'message' => 'Visa Applied Successfully',
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
