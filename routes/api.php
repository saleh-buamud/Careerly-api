<?php

use Illuminate\Support\Facades\Route;

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