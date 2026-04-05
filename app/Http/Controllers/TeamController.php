<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Team;
use App\Models\Department;
use Illuminate\Support\Facades\Storage;

class TeamController extends Controller
{
    // Get all teams
    public function index()
    {
        $teams = Team::with('department')->get()->map(function ($team) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'department_id' => $team->department_id,
                'department_name' => optional($team->department)->department ?? '',
                'image' => $team->image ? asset('storage/' . $team->image) : null,
            ];
        });

        return response()->json(['status' => true, 'data' => $teams]);
    }

    // Get all departments
    public function departments()
    {
        $departments = Department::all();
        return response()->json(['status' => true, 'data' => $departments]);
    }

    // Add a team member
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:teams,name',
            'department_id' => 'required|exists:departments,id',
            'image' => 'nullable|image|max:2048',
        ], [
            'name.unique' => 'This name has already been taken',
        ]);
        $data = $request->only(['name', 'department_id']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('team', 'public');
        }

        $team = Team::create($data);

        return response()->json(['status' => true, 'data' => $team]);
    }

    // Delete a team member
    public function destroy($id)
    {
        $team = Team::find($id);

        if (!$team) {
            return response()->json(['status' => false, 'message' => 'Team member not found'], 404);
        }

        if ($team->image) {
            Storage::disk('public')->delete($team->image);
        }

        $team->delete();

        return response()->json(['status' => true, 'message' => 'Team deleted successfully']);
    }


    public function update(Request $request, $id)
    {
        // 🔒 Check role from request
        if ($request->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Only admin can update.'
            ], 403);
        }

        $team = Team::find($id);

        if (!$team) {
            return response()->json([
                'status' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:teams,name,' . $id,
            'department_id' => 'required|exists:departments,id',
            'image' => 'nullable|image|max:2048',
        ], [
            'name.unique' => 'This name has already been taken',
        ]);

        $team->name = $request->name;
        $team->department_id = $request->department_id;

        if ($request->hasFile('image')) {

            if ($team->image && Storage::disk('public')->exists($team->image)) {
                Storage::disk('public')->delete($team->image);
            }

            $team->image = $request->file('image')->store('team', 'public');
        }

        $team->save();

        return response()->json([
            'status' => true,
            'message' => 'Team updated successfully'
        ]);
    }
}
