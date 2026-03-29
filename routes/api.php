<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VisaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\TeamController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


use App\Http\Controllers\AuthController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\UserInfoController;

Route::post('/login', [AuthController::class, 'login']);

// Protected route (needs valid token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/admin-dashboard', function () {
        return response()->json(['message' => 'Welcome Admin Dashboard']);
    });
});

Route::get('/all-country', [CountryController::class, 'index']);
Route::post('/country/store', [CountryController::class, 'store']);
Route::delete('/delete-country/{id}', [CountryController::class, 'destroy']);

Route::get('/get-header', [UserInfoController::class, 'index']);
Route::post('/add-header', [UserInfoController::class, 'store']);
Route::post('/edit-userInfo/{id}', [UserInfoController::class, 'update']);


Route::middleware('auth:sanctum')->group(function(){

    Route::get('/users',[UserController::class,'index']);

    Route::post('/create-user',[UserController::class,'store']);

    Route::post('/users/{id}/toggle-status',[UserController::class,'toggleStatus']);

});


// Route::post('/add-reviews', [VisaController::class, 'store']);
// Route::get('/get-reviews', [VisaController::class, 'index']);
// Route::delete('/del-reviews/{id}', [VisaController::class, 'destroy']);

// Route::get('/visa-view/{id}', [VisaController::class, 'show']);
// Route::post('/visa-update/{id}', [VisaController::class,'update']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/get-reviews', [VisaController::class, 'index']);
    Route::post('/add-reviews', [VisaController::class, 'store']);
    Route::delete('/del-reviews/{id}', [VisaController::class, 'destroy']);
    Route::get('/visa-view/{id}', [VisaController::class, 'show']);
    Route::post('/visa-update/{id}', [VisaController::class,'update']);

});


Route::get('/departments', [DepartmentController::class, 'index']);
Route::post('/add-department', [DepartmentController::class, 'store']);
Route::delete('/delete-department/{id}', [DepartmentController::class, 'destroy']);

Route::get('/get-team', [TeamController::class, 'index']);

// Add new team member
Route::post('/add-team', [TeamController::class, 'store']);

// Delete a team member
Route::delete('/del-team/{id}', [TeamController::class, 'destroy']);
