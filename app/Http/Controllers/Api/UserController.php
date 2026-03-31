<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    // =========================
    // Get Users
    // =========================
    public function index()
    {
        $users = User::select('id','name','email','role','active')->latest()->get();

        return response()->json([
            'status' => true,
            'users' => $users
        ]);
    }

    // =========================
// Reset User Password
// =========================
public function resetPassword(Request $request, $id)
{
    $request->validate([
        'password' => 'required|min:6|confirmed'
    ]);

    $user = User::findOrFail($id);

    $user->password = Hash::make($request->password);
    $user->save();

    return response()->json([
        'status' => true,
        'message' => 'Password reset successfully'
    ]);
}

    // =========================
    // Create User (Modal Form)
    // =========================
    public function store(Request $request)
    {

        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'active' => true
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'user' => $user
        ]);
    }

    // =========================
    // Toggle Active / Inactive
    // =========================
    public function toggleStatus(Request $request, $id)
    {

        $user = User::findOrFail($id);

        $user->active = $request->active;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'User status updated'
        ]);
    }




    public function me(Request $request)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    return response()->json([
        'status' => true,
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'created_at' => $user->created_at
        ]
    ]);
}
}
