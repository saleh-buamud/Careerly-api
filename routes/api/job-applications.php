<?php

use App\Http\Controllers\Api\JobApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:employer'])->group(function () {
    Route::get('employer/applications', [JobApplicationController::class, 'index']);
    Route::patch('employer/applications/{jobApplication}', [JobApplicationController::class, 'update']);
});

Route::middleware(['auth:sanctum', 'role:job_seeker'])->group(function () {
    Route::post('job-posts/{jobPost}/applications', [JobApplicationController::class, 'store']);
});