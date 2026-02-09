<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\CustomEventField;
use App\Models\CustomEventFieldOption;
use App\Models\CustomEventFieldValue;
use App\Models\Event;
use App\Models\SubCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            ->with([
                'customEventFieldValues.customEventField',
                'customEventFieldValues.customEventFieldOption'
            ])
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

        return EventResource::collection($events);
    }

    /**
     * Store a newly created resource in storage.
     * payload for the custom_event_field_values
     * "custom_event_field_values": [
     * { "custom_event_field_id": 10, "value": "Bring forms" },
     * { "custom_event_field_id": 11, "custom_event_field_option_id": 55 },
     * { "custom_event_field_id": 12, "custom_event_field_option_ids": [61, 62] }
     * ]
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

            // Accept custom field values (supports both snake_case and camelCase keys).
            'custom_event_field_values' => ['sometimes', 'array'],
            'custom_event_field_values.*.custom_event_field_id' => ['required', 'integer'],
            'custom_event_field_values.*.value' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'custom_event_field_values.*.custom_event_field_option_id' => ['sometimes', 'nullable', 'integer'],
            'custom_event_field_values.*.custom_event_field_option_ids' => ['sometimes', 'array'],
            'custom_event_field_values.*.custom_event_field_option_ids.*' => ['integer'],
        ]);

        $subCalendar = SubCalendar::query()
            ->where('id', $data['sub_calendar_id'])
            ->whereHas('calendar', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->firstOrFail();

        $event = DB::transaction(function () use ($data, $subCalendar, $request) {
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

            $incomingValues = $data['custom_event_field_values']
                ?? $request->input('customEventFieldValues')
                ?? null;

            if (is_array($incomingValues)) {
                $this->syncCustomEventFieldValues($event, (int) $subCalendar->calendar_id, $incomingValues);
            }

            return $event;
        });

        $event->load([
            'customEventFieldValues.customEventField',
            'customEventFieldValues.customEventFieldOption'
        ]);

        return new EventResource($event);
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
            ->with([
                'customEventFieldValues.customEventField',
                'customEventFieldValues.customEventFieldOption'
            ])
            ->firstOrFail();

        return new EventResource($event);
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

            // Accept custom field values (supports both snake_case and camelCase keys).
            'custom_event_field_values' => ['sometimes', 'array'],
            'custom_event_field_values.*.custom_event_field_id' => ['required', 'integer'],
            'custom_event_field_values.*.value' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'custom_event_field_values.*.custom_event_field_option_id' => ['sometimes', 'nullable', 'integer'],
            'custom_event_field_values.*.custom_event_field_option_ids' => ['sometimes', 'array'],
            'custom_event_field_values.*.custom_event_field_option_ids.*' => ['integer'],
        ]);

        if (array_key_exists('start_date', $data) || array_key_exists('end_date', $data)) {
            $request->validate([
                'end_date' => ['after_or_equal:start_date'],
            ]);
        }

        DB::transaction(function () use ($event, $data, $request) {
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

            $incomingValues = $data['custom_event_field_values']
                ?? $request->input('customEventFieldValues')
                ?? null;

            if (is_array($incomingValues)) {
                $calendarId = (int) $event->subCalendar()->value('calendar_id');
                $this->syncCustomEventFieldValues($event, $calendarId, $incomingValues);
            }
        });

        $event->load([
            'customEventFieldValues.customEventField',
            'customEventFieldValues.customEventFieldOption'
        ]);


        return new EventResource($event);
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

    /**
     * Replaces per-field values for the event.
     *
     * Payload supports:
     * - text:   { custom_event_field_id, value }
     * - single: { custom_event_field_id, custom_event_field_option_id }
     * - multi:  { custom_event_field_id, custom_event_field_option_ids: [1,2] }
     *
     * Clearing:
     * - text: value null/"" clears
     * - s_select: option_id null clears
     * - m_select: option_ids [] clears
     *
     * @param array<int, array<string, mixed>> $incomingValues
     */
    private function syncCustomEventFieldValues(Event $event, int $calendarId, array $incomingValues): void
    {
        // De-dupe by field id: last one wins.
        $byFieldId = [];
        foreach ($incomingValues as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!array_key_exists('custom_event_field_id', $row)) {
                continue;
            }
            $fieldId = (int) $row['custom_event_field_id'];
            if ($fieldId <= 0) {
                continue;
            }
            $byFieldId[$fieldId] = $row;
        }

        if ($byFieldId === []) {
            return;
        }

        $fields = CustomEventField::query()
            ->where('calendar_id', $calendarId)
            ->whereIn('id', array_keys($byFieldId))
            ->get()
            ->keyBy('id');

        $missing = array_values(array_diff(array_keys($byFieldId), $fields->keys()->all()));
        if ($missing !== []) {
            abort(response()->json([
                'message' => 'Validation error.',
                'errors' => [
                    'custom_event_field_values' => ['One or more custom_event_field_id values are invalid for this calendar.'],
                ],
            ], 422));
        }

        foreach ($byFieldId as $fieldId => $row) {
            /** @var CustomEventField $field */
            $field = $fields->get($fieldId);

            // Always replace stored values for this field.
            CustomEventFieldValue::query()
                ->where('event_id', $event->id)
                ->where('custom_event_field_id', $fieldId)
                ->delete();

            if ($field->type === 'text') {
                $value = array_key_exists('value', $row) ? $row['value'] : null;
                $value = is_string($value) ? trim($value) : null;

                if ($value === null || $value === '') {
                    continue; // cleared
                }

                CustomEventFieldValue::create([
                    'event_id' => $event->id,
                    'custom_event_field_id' => $fieldId,
                    'value' => $value,
                    'custom_event_field_option_id' => null,
                ]);

                continue;
            }

            if ($field->type === 's_select') {
                $optId = $row['custom_event_field_option_id'] ?? null;
                $optId = $optId !== null ? (int) $optId : null;

                if (empty($optId)) {
                    continue; // cleared
                }

                $valid = CustomEventFieldOption::query()
                    ->where('id', $optId)
                    ->where('custom_event_field_id', $fieldId)
                    ->exists();

                if (!$valid) {
                    abort(response()->json([
                        'message' => 'Validation error.',
                        'errors' => [
                            'custom_event_field_values' => ["Invalid option for custom_event_field_id {$fieldId}."],
                        ],
                    ], 422));
                }

                CustomEventFieldValue::create([
                    'event_id' => $event->id,
                    'custom_event_field_id' => $fieldId,
                    'value' => null,
                    'custom_event_field_option_id' => $optId,
                ]);

                continue;
            }

            if ($field->type === 'm_select') {
                $optIds = $row['custom_event_field_option_ids'] ?? [];
                if (!is_array($optIds)) {
                    abort(response()->json([
                        'message' => 'Validation error.',
                        'errors' => [
                            'custom_event_field_values' => ["custom_event_field_option_ids must be an array for custom_event_field_id {$fieldId}."],
                        ],
                    ], 422));
                }

                $optIds = array_values(array_unique(array_filter(array_map(
                    static fn ($v) => (int) $v,
                    $optIds
                ), static fn (int $v) => $v > 0)));

                if ($optIds === []) {
                    continue; // cleared
                }

                $validCount = CustomEventFieldOption::query()
                    ->where('custom_event_field_id', $fieldId)
                    ->whereIn('id', $optIds)
                    ->count();

                if ($validCount !== count($optIds)) {
                    abort(response()->json([
                        'message' => 'Validation error.',
                        'errors' => [
                            'custom_event_field_values' => ["One or more options are invalid for custom_event_field_id {$fieldId}."],
                        ],
                    ], 422));
                }

                foreach ($optIds as $optId) {
                    CustomEventFieldValue::create([
                        'event_id' => $event->id,
                        'custom_event_field_id' => $fieldId,
                        'value' => null,
                        'custom_event_field_option_id' => $optId,
                    ]);
                }

                continue;
            }

            // Unknown type: treat as validation error (future-proofing).
            abort(response()->json([
                'message' => 'Validation error.',
                'errors' => [
                    'custom_event_field_values' => ["Unsupported field type for custom_event_field_id {$fieldId}."],
                ],
            ], 422));
        }
    }
}
