<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class DepartmentController extends Controller
{
    // List all departments
    public function index()
    {
        try {
            $departments = Department::orderBy('id', 'desc')->get();

            return response()->json([
                'status' => true,
                'data' => $departments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch departments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Add a department
    public function store(Request $request)
    {
        try {
            $request->validate([
                'department' => 'required|string|max:255|unique:departments,department',
            ]);

            $department = Department::create([
                'department' => $request->department
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Department added successfully',
                'data' => $department
            ]);
        } catch (ValidationException $ve) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $ve->errors()
            ], 422);
        } catch (QueryException $qe) {
            return response()->json([
                'status' => false,
                'message' => 'Database error',
                'error' => $qe->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete a department
    public function destroy($id)
    {
        try {
            $department = Department::find($id);

            if (!$department) {
                return response()->json([
                    'status' => false,
                    'message' => 'Department not found'
                ], 404);
            }

            $department->delete();

            return response()->json([
                'status' => true,
                'message' => 'Department deleted successfully'
            ]);
        } catch (QueryException $qe) {
            return response()->json([
                'status' => false,
                'message' => 'Database error',
                'error' => $qe->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
