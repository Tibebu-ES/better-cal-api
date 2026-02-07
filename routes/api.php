<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

//auth
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('auth/logout', [AuthController::class, 'logout']);

