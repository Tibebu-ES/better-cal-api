<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\SubCalendar;
use Illuminate\Http\Request;

class SubCalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = SubCalendar::query()
            ->whereHas('calendar', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orderByDesc('id');

        if ($request->filled('calendar_id')) {
            $query->where('calendar_id', (int) $request->input('calendar_id'));
        }

        $subCalendars = $query->paginate((int) $request->integer('per_page', 15));

        return response()->json($subCalendars);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'calendar_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'overlap' => ['sometimes', 'boolean'],
            'color' => ['sometimes', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $calendar = Calendar::query()
            ->where('id', $data['calendar_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $subCalendar = SubCalendar::create([
            'calendar_id' => $calendar->id,
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
    public function show(Request $request, string $id)
    {
        $subCalendar = SubCalendar::query()
            ->where('id', $id)
            ->whereHas('calendar', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        return response()->json($subCalendar);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $subCalendar = SubCalendar::query()
            ->where('id', $id)
            ->whereHas('calendar', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        $data = $request->validate([
            'calendar_id' => ['sometimes', 'integer'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'overlap' => ['sometimes', 'boolean'],
            'color' => ['sometimes', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        if (array_key_exists('calendar_id', $data)) {
            $calendar = Calendar::query()
                ->where('id', $data['calendar_id'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $data['calendar_id'] = $calendar->id;
        }

        $subCalendar->fill($data);
        $subCalendar->save();

        return response()->json($subCalendar);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $subCalendar = SubCalendar::query()
            ->where('id', $id)
            ->whereHas('calendar', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        $subCalendar->delete();

        return response()->json(null, 204);
    }
}
