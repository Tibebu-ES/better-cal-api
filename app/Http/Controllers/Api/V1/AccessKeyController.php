<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccessKey;
use App\Models\Calendar;
use App\Models\SubCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccessKeyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = AccessKey::query()
            ->with(['subCalendarPermissions'])
            ->whereHas('calendar', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orderByDesc('id');

        if ($request->filled('calendar_id')) {
            $query->where('calendar_id', (int) $request->input('calendar_id'));
        }

        $accessKeys = $query->paginate((int) $request->integer('per_page', 15));

        $accessKeys->getCollection()->transform(function (AccessKey $k) {
            return $k->makeHidden(['password']);
        });

        return response()->json($accessKeys);
    }

    /**
     * Store a newly created resource in storage.
     * Payload shape for permissions
     * {
     * "sub_calendar_permissions": [
     * { "sub_calendar_id": 123, "access_type": "read_only" },
     * { "sub_calendar_id": 124, "access_type": "modify" }
     * ]
     * }
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'calendar_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],

            'has_password' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],

            'sub_calendar_permissions' => ['sometimes', 'array'],
            'sub_calendar_permissions.*.sub_calendar_id' => ['required', 'integer'],
            'sub_calendar_permissions.*.access_type' => ['required', Rule::in(['read_only', 'modify'])],
        ]);

        $calendar = Calendar::query()
            ->where('id', $data['calendar_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $hasPassword = (bool) ($data['has_password'] ?? false);
        $password = $data['password'] ?? null;

        if ($hasPassword && ($password === null || trim((string) $password) === '')) {
            return response()->json([
                'message' => 'Validation error.',
                'errors' => [
                    'password' => ['Password is required when has_password is true.'],
                ],
            ], 422);
        }

        $incomingPerms = $data['sub_calendar_permissions'] ?? [];
        $this->assertValidSubCalendarPermissions($calendar->id, $incomingPerms);

        $accessKey = DB::transaction(function () use ($calendar, $data, $hasPassword, $password, $incomingPerms) {
            $accessKey = AccessKey::create([
                'calendar_id' => $calendar->id,
                'name' => $data['name'],
                'key' => $this->generateUniqueAccessKey(),
                'active' => $data['active'] ?? true,
                'has_password' => $hasPassword,
                'password' => $hasPassword ? Hash::make((string) $password) : null,
            ]);

            if ($incomingPerms !== []) {
                $accessKey->subCalendarPermissions()->createMany(array_map(function (array $row) use ($accessKey) {
                    return [
                        'sub_calendar_id' => (int) $row['sub_calendar_id'],
                        'access_key_id' => $accessKey->id,
                        'access_type' => $row['access_type'],
                    ];
                }, $incomingPerms));
            }

            return $accessKey;
        });

        $accessKey->load(['subCalendarPermissions']);

        return response()->json($accessKey->makeHidden(['password']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $accessKey = AccessKey::query()
            ->with(['subCalendarPermissions'])
            ->where('id', $id)
            ->whereHas('calendar', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        return response()->json($accessKey->makeHidden(['password']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $accessKey = AccessKey::query()
            ->where('id', $id)
            ->whereHas('calendar', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        $data = $request->validate([
            'calendar_id' => ['sometimes', 'integer'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],

            'has_password' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],

            'sub_calendar_permissions' => ['sometimes', 'array'],
            'sub_calendar_permissions.*.sub_calendar_id' => ['required', 'integer'],
            'sub_calendar_permissions.*.access_type' => ['required', Rule::in(['read_only', 'modify'])],
        ]);

        $newCalendarId = (int) ($data['calendar_id'] ?? $accessKey->calendar_id);

        if (array_key_exists('calendar_id', $data)) {
            $calendar = Calendar::query()
                ->where('id', $newCalendarId)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $newCalendarId = (int) $calendar->id;

            if (!array_key_exists('sub_calendar_permissions', $data)) {
                return response()->json([
                    'message' => 'Validation error.',
                    'errors' => [
                        'sub_calendar_permissions' => ['sub_calendar_permissions is required when changing calendar_id.'],
                    ],
                ], 422);
            }
        }

        $incomingPerms = $data['sub_calendar_permissions'] ?? null;
        if (is_array($incomingPerms)) {
            $this->assertValidSubCalendarPermissions($newCalendarId, $incomingPerms);
        }

        DB::transaction(function () use ($accessKey, $data, $newCalendarId, $incomingPerms) {
            if (array_key_exists('calendar_id', $data)) {
                $accessKey->calendar_id = $newCalendarId;
            }

            if (array_key_exists('name', $data)) {
                $accessKey->name = $data['name'];
            }

            if (array_key_exists('active', $data)) {
                $accessKey->active = (bool) $data['active'];
            }

            // Password logic
            if (array_key_exists('has_password', $data) || array_key_exists('password', $data)) {
                $hasPassword = array_key_exists('has_password', $data)
                    ? (bool) $data['has_password']
                    : (bool) $accessKey->has_password;

                $password = $data['password'] ?? null;

                if ($hasPassword) {
                    if (array_key_exists('password', $data)) {
                        if ($password === null || trim((string) $password) === '') {
                            abort(response()->json([
                                'message' => 'Validation error.',
                                'errors' => [
                                    'password' => ['Password cannot be empty when has_password is true.'],
                                ],
                            ], 422));
                        }

                        $accessKey->has_password = true;
                        $accessKey->password = Hash::make((string) $password);
                    } else {
                        // has_password true but no new password provided -> keep existing hash
                        $accessKey->has_password = true;
                    }
                } else {
                    $accessKey->has_password = false;
                    $accessKey->password = null;
                }
            }

            $accessKey->save();

            // Permissions sync (only if provided)
            if (is_array($incomingPerms)) {
                $accessKey->subCalendarPermissions()->delete();

                if ($incomingPerms !== []) {
                    $accessKey->subCalendarPermissions()->createMany(array_map(function (array $row) use ($accessKey) {
                        return [
                            'sub_calendar_id' => (int) $row['sub_calendar_id'],
                            'access_key_id' => $accessKey->id,
                            'access_type' => $row['access_type'],
                        ];
                    }, $incomingPerms));
                }
            }
        });

        $accessKey->load(['subCalendarPermissions']);

        return response()->json($accessKey->makeHidden(['password']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $accessKey = AccessKey::query()
            ->where('id', $id)
            ->whereHas('calendar', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        $accessKey->delete();

        return response()->json(null, 204);
    }

    /**
     * @param array<int, array<string, mixed>> $incomingPerms
     */
    private function assertValidSubCalendarPermissions(int $calendarId, array $incomingPerms): void
    {
        if ($incomingPerms === []) {
            return;
        }

        $subCalendarIds = [];
        foreach ($incomingPerms as $row) {
            if (!is_array($row) || !array_key_exists('sub_calendar_id', $row)) {
                continue;
            }
            $subCalendarIds[] = (int) $row['sub_calendar_id'];
        }

        $subCalendarIds = array_values(array_unique(array_filter($subCalendarIds, static fn (int $v) => $v > 0)));

        if ($subCalendarIds === []) {
            return;
        }

        $validCount = SubCalendar::query()
            ->where('calendar_id', $calendarId)
            ->whereIn('id', $subCalendarIds)
            ->count();

        if ($validCount !== count($subCalendarIds)) {
            abort(response()->json([
                'message' => 'Validation error.',
                'errors' => [
                    'sub_calendar_permissions' => ['One or more sub_calendar_id values are invalid for this calendar.'],
                ],
            ], 422));
        }
    }

    private function generateUniqueAccessKey(): string
    {
        // 40 chars is plenty; loop in the extremely unlikely event of a collision.
        for ($i = 0; $i < 5; $i++) {
            $key = Str::random(40);

            $exists = AccessKey::query()
                ->where('key', $key)
                ->exists();

            if (!$exists) {
                return $key;
            }
        }

        // If we somehow collide repeatedly, make it longer.
        return Str::random(80);
    }
}
