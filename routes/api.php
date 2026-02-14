<?php

use App\Http\Controllers\Api\V1\AccessKeyController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\CustomEventFieldController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\SubCalendarController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('v1')->group(function () {
        Route::apiResource('calendars', CalendarController::class);

        Route::scopeBindings()->group(function () {
            Route::apiResource('calendars.sub-calendars', SubCalendarController::class)
                ->parameters(['sub-calendars' => 'subCalendar']);
            Route::apiResource('calendars.events', EventController::class)
                ->parameters(['events' => 'event']);
        });


        Route::apiResource('custom-event-fields', CustomEventFieldController::class);
        Route::apiResource('access-keys', AccessKeyController::class);
    });
});

require __DIR__.'/auth.php';
