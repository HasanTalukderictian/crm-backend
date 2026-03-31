<?php

use App\Http\Controllers\Api\NotificationController;
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
use App\Http\Controllers\TargetController;
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
    Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);

    Route::post('/create-user',[UserController::class,'store']);

    Route::post('/users/{id}/toggle-status',[UserController::class,'toggleStatus']);

});


Route::post('/set-target', [TargetController::class, 'store']);

Route::get('/get-target', [TargetController::class, 'index']);
Route::post('/targets/{id}', [TargetController::class, 'update']);

Route::middleware('auth:sanctum')->get('/monthly-achieved', [TargetController::class, 'monthlyAchieved']);
Route::middleware('auth:sanctum')->get('/top-users-achieved', [TargetController::class, 'topUsersByAchieved']);
Route::middleware('auth:sanctum')->get('/achieved-summary', [TargetController::class, 'achievedSummary']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/get-reviews', [VisaController::class, 'index']);
    Route::post('/add-reviews', [VisaController::class, 'store']);
    Route::delete('/del-reviews/{id}', [VisaController::class, 'destroy']);
    Route::get('/visa-view/{id}', [VisaController::class, 'show']);
    Route::post('/visa-update/{id}', [VisaController::class,'update']);

    Route::get('/monthly-visa-stats', [VisaController::class, 'monthlyVisaStats']);

});


Route::middleware('auth:sanctum')->group(function () {

    Route::post('/send-notification', [NotificationController::class, 'send']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

});



Route::get('/departments', [DepartmentController::class, 'index']);
Route::post('/add-department', [DepartmentController::class, 'store']);
Route::delete('/delete-department/{id}', [DepartmentController::class, 'destroy']);

Route::get('/get-team', [TeamController::class, 'index']);

// Add new team member
Route::post('/add-team', [TeamController::class, 'store']);

// Delete a team member
Route::delete('/del-team/{id}', [TeamController::class, 'destroy']);
