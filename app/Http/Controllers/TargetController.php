<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Target;
use Illuminate\Http\Request;

class TargetController extends Controller
{
    //


    public function index(Request $request)
{
    $query = Target::with('user');

    // Optional filters
    if ($request->user_id) {
        $query->where('user_id', $request->user_id);
    }

    if ($request->year) {
        $query->where('year', $request->year);
    }

    if ($request->month) {
        $query->where('month', $request->month);
    }

    $targets = $query->orderBy('id', 'desc')->paginate(10);

    return response()->json([
        'status' => true,
        'data' => $targets
    ]);
}



public function store(Request $request)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'target'  => 'required|integer|min:1',
        'year'    => 'required|integer',
        'month'   => 'required|integer|min:1|max:12',
    ]);

    // 🔥 check if already exists
    $exists = Target::where('user_id', $request->user_id)
        ->where('year', $request->year)
        ->where('month', $request->month)
        ->exists();

    if ($exists) {
        return response()->json([
            'status' => false,
            'message' => 'Target already set for this user in this month'
        ], 409);
    }

    // create only (no update allowed)
    $target = Target::create([
        'user_id' => $request->user_id,
        'target'  => $request->target,
        'year'    => $request->year,
        'month'   => $request->month,
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Target set successfully',
        'data' => $target
    ]);
}


public function update(Request $request, $id)
{
    $request->validate([
        'target'  => 'required|integer|min:1',
        'year'    => 'required|integer',
        'month'   => 'required|integer|min:1|max:12',
    ]);

    $target = Target::find($id);

    if (!$target) {
        return response()->json([
            'status' => false,
            'message' => 'Target not found'
        ], 404);
    }

    // 🔥 Optional: prevent duplicate (same user, year, month)
    $exists = Target::where('user_id', $target->user_id)
        ->where('year', $request->year)
        ->where('month', $request->month)
        ->where('id', '!=', $id)
        ->exists();

    if ($exists) {
        return response()->json([
            'status' => false,
            'message' => 'Another target already exists for this user in this month'
        ], 409);
    }

    $target->update([
        'target' => $request->target,
        'year'   => $request->year,
        'month'  => $request->month,
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Target updated successfully',
        'data' => $target
    ]);
}





}
