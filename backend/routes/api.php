<?php

use App\Http\Controllers\ApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->patch(
    '/applications/{application}/review',
    [ApplicationController::class, 'review']
);