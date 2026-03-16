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

}
