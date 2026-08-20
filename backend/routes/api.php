<?php

use App\Http\Controllers\ApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post(
        '/job-postings/{jobPosting}/applications',
        [ApplicationController::class, 'store']
    );

    Route::patch(
        '/applications/{application}/review',
        [ApplicationController::class, 'review']
    );
});