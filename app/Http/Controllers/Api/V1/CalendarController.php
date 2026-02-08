<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $calendars = Calendar::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json($calendars);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'about' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $calendar = Calendar::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'active' => $data['active'] ?? true,
            'about' => $data['about'] ?? null,
            'timezone' => $data['timezone'] ?? 'UTC',
            'locale' => $data['locale'] ?? 'en',
        ]);

        return response()->json($calendar, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $calendar = Calendar::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json($calendar);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $calendar = Calendar::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'about' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $calendar->fill($data);
        $calendar->save();

        return response()->json($calendar);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $calendar = Calendar::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $calendar->delete();

        return response()->json(null, 204);
    }
}
