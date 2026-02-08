<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SubCalendar;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Event::query()
            ->whereHas('subCalendar.calendar', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orderByDesc('id');

        if ($request->filled('sub_calendar_id')) {
            $query->where('sub_calendar_id', (int) $request->input('sub_calendar_id'));
        }

        if ($request->filled('calendar_id')) {
            $calendarId = (int) $request->input('calendar_id');

            $query->whereHas('subCalendar', function ($q) use ($calendarId) {
                $q->where('calendar_id', $calendarId);
            });
        }

        if ($request->filled('start_from')) {
            $request->validate([
                'start_from' => ['date'],
            ]);

            $query->where('start_date', '>=', $request->input('start_from'));
        }

        if ($request->filled('start_to')) {
            $request->validate([
                'start_to' => ['date'],
            ]);

            $query->where('start_date', '<=', $request->input('start_to'));
        }

        $events = $query->paginate((int) $request->integer('per_page', 15));

        return response()->json($events);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'sub_calendar_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'all_day' => ['sometimes', 'boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'rrule' => ['sometimes', 'nullable', 'string', 'max:255'],
            'about' => ['sometimes', 'nullable', 'string', 'max:255'],
            'where' => ['sometimes', 'nullable', 'string', 'max:255'],
            'who' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $subCalendar = SubCalendar::query()
            ->where('id', $data['sub_calendar_id'])
            ->whereHas('calendar', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->firstOrFail();

        $event = Event::create([
            'sub_calendar_id' => $subCalendar->id,
            'title' => $data['title'],
            'all_day' => $data['all_day'] ?? false,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'rrule' => $data['rrule'] ?? null,
            'about' => $data['about'] ?? null,
            'where' => $data['where'] ?? null,
            'who' => $data['who'] ?? null,
        ]);

        return response()->json($event, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $event = Event::query()
            ->where('id', $id)
            ->whereHas('subCalendar.calendar', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        return response()->json($event);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $event = Event::query()
            ->where('id', $id)
            ->whereHas('subCalendar.calendar', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        $data = $request->validate([
            'sub_calendar_id' => ['sometimes', 'integer'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'all_day' => ['sometimes', 'boolean'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date'],
            'rrule' => ['sometimes', 'nullable', 'string', 'max:255'],
            'about' => ['sometimes', 'nullable', 'string', 'max:255'],
            'where' => ['sometimes', 'nullable', 'string', 'max:255'],
            'who' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('start_date', $data) || array_key_exists('end_date', $data)) {
            $request->validate([
                'end_date' => ['after_or_equal:start_date'],
            ]);
        }

        if (array_key_exists('sub_calendar_id', $data)) {
            $subCalendar = SubCalendar::query()
                ->where('id', $data['sub_calendar_id'])
                ->whereHas('calendar', function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                })
                ->firstOrFail();

            $data['sub_calendar_id'] = $subCalendar->id;
        }

        $event->fill($data);
        $event->save();

        return response()->json($event);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $event = Event::query()
            ->where('id', $id)
            ->whereHas('subCalendar.calendar', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        $event->delete();

        return response()->json(null, 204);
    }
}
