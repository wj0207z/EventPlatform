<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\CrewProfileController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/login',
    [AuthController::class, 'login']
);

Route::post(
    '/register',
    [AuthController::class, 'register']
);

Route::middleware('auth:sanctum')->group(function () {
    Route::post(
        '/job-postings/{jobPosting}/applications',
        [ApplicationController::class, 'store']
    );

    Route::patch(
        '/applications/{application}/review',
        [ApplicationController::class, 'review']
    );

    Route::get(
        '/my-applications',
        [ApplicationController::class, 'myApplications']
    );

    Route::get(
        '/recruiter-applications',
        [ApplicationController::class, 'recruiterApplications']
    );

    Route::put(
        '/me/crew-profile',
        [CrewProfileController::class, 'update']
    );

    Route::get(
        '/me',
        [AuthController::class, 'me']
    );

    Route::post(
        '/logout',
        [AuthController::class, 'logout']
    );

    Route::get(
        '/job-postings',
        [JobPostingController::class, 'index']
    );

    Route::post(
        '/job-postings',
        [JobPostingController::class, 'store']
    );

    Route::get(
        '/events',
        [EventController::class, 'index']
    );
});