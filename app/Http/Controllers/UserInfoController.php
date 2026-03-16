<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserInfo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class UserInfoController extends Controller
{
    // Get all user info
    public function index()
    {
        $data = UserInfo::all();
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // Store new user info
   public function store(Request $request)
{
    $request->validate([
        'company_name' => 'required|string|max:255|unique:user_infos,company_name',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    $userInfo = new UserInfo();
    $userInfo->company_name = $request->company_name;

    if ($request->hasFile('image')) {
        $path = $request->file('image')->store('user_info', 'public');
        $userInfo->image = URL::to('/') . '/storage/' . $path;
    }

    $userInfo->save();

    return response()->json([
        'status' => true,
        'message' => 'User info stored successfully!',
        'data' => $userInfo
    ]);
}

    // Update existing user info
    public function update(Request $request, $id)
    {
        $request->validate([
            'company_name' => 'required|string|max:255|unique:user_infos,company_name,'.$id,
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $userInfo = UserInfo::findOrFail($id);

        if ($request->hasFile('image')) {
            // delete old image if exists
            if ($userInfo->image && Storage::exists('public/'.$userInfo->image)) {
                Storage::delete('public/'.$userInfo->image);
            }

            $path = $request->file('image')->store('user_info', 'public');
            $userInfo->image = $path;
        }

        $userInfo->company_name = $request->company_name;
        $userInfo->save();

        return response()->json([
            'status' => true,
            'message' => 'User info updated successfully!',
            'data' => $userInfo
        ]);
    }
}
