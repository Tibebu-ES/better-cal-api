<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\SubCalendar;
use Illuminate\Http\Request;

class SubCalendarController extends Controller
{
    private function assertCalendarOwnedByUser(Request $request, Calendar $calendar): void
    {
        abort_unless($calendar->user_id === $request->user()->id, 404);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Calendar $calendar)
    {
        $this->assertCalendarOwnedByUser($request, $calendar);

        $subCalendars = $calendar->subCalendars()
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json($subCalendars);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Calendar $calendar)
    {
        $this->assertCalendarOwnedByUser($request, $calendar);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'overlap' => ['sometimes', 'boolean'],
            'color' => ['sometimes', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $subCalendar = $calendar->subCalendars()->create([
            'name' => $data['name'],
            'active' => $data['active'] ?? true,
            'overlap' => $data['overlap'] ?? false,
            'color' => $data['color'] ?? '#000000',
        ]);

        return response()->json($subCalendar, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Calendar $calendar, SubCalendar $subCalendar)
    {
        $this->assertCalendarOwnedByUser($request, $calendar);

        return response()->json($subCalendar);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Calendar $calendar, SubCalendar $subCalendar)
    {
        $this->assertCalendarOwnedByUser($request, $calendar);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'overlap' => ['sometimes', 'boolean'],
            'color' => ['sometimes', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $subCalendar->fill($data);
        $subCalendar->save();

        return response()->json($subCalendar);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Calendar $calendar, SubCalendar $subCalendar)
    {
        $this->assertCalendarOwnedByUser($request, $calendar);

        $subCalendar->delete();

        return response()->json(null, 204);
    }
}
