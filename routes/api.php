<?php

use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\CustomEventFieldController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\SubCalendarController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('v1')->group(function () {
        Route::apiResource('calendars', CalendarController::class);
        Route::apiResource('sub-calendars', SubCalendarController::class);
        Route::apiResource('events', EventController::class);
        Route::apiResource('custom-event-fields', CustomEventFieldController::class);
    });

});

require __DIR__.'/auth.php';
