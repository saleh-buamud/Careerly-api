<?php

use App\Http\Controllers\Api\JobPostController;
use Illuminate\Support\Facades\Route;

Route::get('job-posts', [JobPostController::class, 'index']);
Route::get('job-posts/{jobPost}', [JobPostController::class, 'show']);

Route::middleware(['auth:sanctum', 'role:employer'])->group(function () {
    Route::post('job-posts', [JobPostController::class, 'store']);
    Route::put('job-posts/{jobPost}', [JobPostController::class, 'update']);
    Route::delete('job-posts/{jobPost}', [JobPostController::class, 'destroy']);
});