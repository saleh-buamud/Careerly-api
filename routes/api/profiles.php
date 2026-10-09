<?php

use App\Http\Controllers\Api\EmployerProfileController;
use App\Http\Controllers\Api\JobSeekerProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('employer-profile', [EmployerProfileController::class, 'show']);
    Route::post('employer-profile', [EmployerProfileController::class, 'store']);
    Route::put('employer-profile', [EmployerProfileController::class, 'update']);

    Route::get('job-seeker-profile', [JobSeekerProfileController::class, 'show']);
    Route::post('job-seeker-profile', [JobSeekerProfileController::class, 'store']);
    Route::put('job-seeker-profile', [JobSeekerProfileController::class, 'update']);
});