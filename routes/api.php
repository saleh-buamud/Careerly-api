<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:120,1')->group(function (): void {
    require __DIR__ . '/api/auth.php';
    require __DIR__ . '/api/job-posts.php';
    require __DIR__ . '/api/job-applications.php';
    require __DIR__ . '/api/profiles.php';
    require __DIR__ . '/api/admin.php';

    Route::get('test', function () {
        return response()->json([
            'message' => 'API is working',
        ]);
    });
});