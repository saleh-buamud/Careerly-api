<?php

use App\Http\Controllers\Api\JobCategoryController;
use App\Http\Controllers\Api\SkillController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::apiResource('job-categories', JobCategoryController::class);
    Route::apiResource('skills', SkillController::class);
});