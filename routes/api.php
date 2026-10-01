<?php

use App\Http\Controllers\Api\EmployerProfileController;
use App\Http\Controllers\Api\JobCategoryController;
use App\Http\Controllers\Api\JobPostController;
use App\Http\Controllers\Api\JobSeekerProfileController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Route::apiResource('job-posts', JobPostController::class)
//     ->parameters(['job-posts' => 'jobPost']);
Route::get('job-posts', [JobPostController::class, 'index']);
Route::get('job-posts/{jobPost}', [JobPostController::class, 'show']);
Route::put('job-posts/{jobPost}', [JobPostController::class, 'update'])
    ->middleware('auth:sanctum');
Route::delete('job-posts/{jobPost}', [JobPostController::class, 'destroy'])
    ->middleware('auth:sanctum');
Route::post('job-posts', [JobPostController::class, 'store'])
    ->middleware('auth:sanctum');
Route::apiResource('job-categories', JobCategoryController::class);
Route::apiResource('skills', SkillController::class);
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::get('test', function () {
    return response()->json(['message' => 'API is working']);
});
Route::get('employer-profile', [EmployerProfileController::class, 'show']);
Route::get('job-seeker-profile', [JobSeekerProfileController::class, 'show']);
