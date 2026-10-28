<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\CrewProfileController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;

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

    Route::get(
        '/job-postings/{jobPosting}/applications',
        [ApplicationController::class, 'jobApplications']
    );

    Route::patch(
        '/applications/{application}/withdraw',
        [ApplicationController::class, 'withdraw']
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
        '/job-postings/{jobPosting}',
        [JobPostingController::class, 'show']
    );

    Route::patch(
        '/job-postings/{jobPosting}',
        [JobPostingController::class, 'update']
    );

    Route::delete(
        '/job-postings/{jobPosting}',
        [JobPostingController::class, 'destroy']
    );

    Route::get(
        '/recruiter-job-postings',
        [JobPostingController::class, 'recruiterJobs']
    );

    Route::get(
        '/events',
        [EventController::class, 'index']
    );

    Route::post(
        '/events',
        [EventController::class, 'store']
    );

    Route::get(
        '/events/{event}',
        [EventController::class, 'show']
    );

    Route::patch(
        '/events/{event}',
        [EventController::class, 'update']
    );

    Route::put(
        '/me/crew-profile',
        [CrewProfileController::class, 'update']
    );

    Route::get(
        '/clients',
        [ClientController::class, 'index']
    );

    Route::post(
        '/clients',
        [ClientController::class, 'store']
    );

});