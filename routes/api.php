<?php

use App\Http\Controllers\Api\V1\AccessKeyController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\CustomEventFieldController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\SubCalendarController;
use App\Models\AccessKey;
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

            /*Route::apiResource('calendars.events', EventController::class)
                ->parameters(['events' => 'event']);*/

            Route::apiResource('calendars.custom-event-fields', CustomEventFieldController::class)
                ->parameters(['custom-event-fields' => 'customEventField']);

            Route::apiResource('calendars.access-keys', AccessKeyController::class)
                ->parameters(['access-keys' => 'accessKey']);
        });

    });
});
Route::prefix('v1')->group(function () {

    Route::get('/events/access-key-details', function (Request $request) {
        $key = $request->header('X-Access-Key');
        //get access key details from AccessKey model using the key field
        //with subCalendarPermissions
        $accessKey = AccessKey::where('key', $key)->with('subCalendarPermissions')->firstorfail();
        $calendar = $accessKey->calendar()->first();
        $subCalendars = $calendar->subCalendars()->get();
        $customEventFields = $calendar->customEventFields()->with(['options'])->get();
        return response()->json(['access_key' => $accessKey, 'calendar' => $calendar,'sub_calendars' => $subCalendars, 'custom_event_fields' => $customEventFields]);
    });
    Route::apiResource('events', EventController::class);

});

require __DIR__.'/auth.php';
