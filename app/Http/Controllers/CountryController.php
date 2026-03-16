<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    //

    // Fetch all countries
    public function index()
    {
        $countries = Country::orderBy('id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $countries]);
    }

    // Store country
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:countries,name|max:255',
        ]);

        $country = Country::create([
            'name' => $request->name
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Country created successfully',
            'data' => $country
        ]);
    }

    // Delete country
    public function destroy($id)
    {
        $country = Country::find($id);
        if (!$country) {
            return response()->json(['success' => false, 'message' => 'Country not found'], 404);
        }

        $country->delete();

        return response()->json(['success' => true, 'message' => 'Country deleted successfully']);
    }
}
