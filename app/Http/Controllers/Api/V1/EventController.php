<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\AccessKey;
use App\Models\Calendar;
use App\Models\CustomEventField;
use App\Models\CustomEventFieldOption;
use App\Models\CustomEventFieldValue;
use App\Models\Event;
use App\Models\SubCalendar;
use App\Models\SubCalendarPermission;
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
        $key = $request->header('X-Access-Key');
        $accessKey = AccessKey::where('key', $key)->firstorfail();

        $calendar = $accessKey->calendar;

        // only events in the sub-calendars the key has read or modify access if the shared type is selected_sub_calendars
        $subCalendarsIdsToInclude = $accessKey->shared_type == 'selected_sub_calendars' ? $accessKey->subCalendarPermissions()->whereIn('access_type',['read_only','modify'])->pluck('sub_calendar_id') : [];


        $query = $calendar->events()
            ->with([
                'customEventFieldValues.customEventField',
                'customEventFieldValues.customEventFieldOption',
            ])
            ->when($accessKey->shared_type == 'selected_sub_calendars', fn ($q) => $q->whereIn('sub_calendar_id',$subCalendarsIdsToInclude))
            ->orderByDesc('id');

        if ($request->filled('sub_calendar_id')) {
            $subCalendarId = (int) $request->input('sub_calendar_id');

            $query->whereHas('subCalendar', function ($q) use ($subCalendarId, $calendar) {
                $q->where('id', $subCalendarId)
                    ->where('calendar_id', $calendar->id);
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

        $events = $query->get();

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
        $key = $request->header('X-Access-Key');
        $accessKey = AccessKey::where('key', $key)->firstorfail();

        $calendar = $accessKey->calendar;

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
            ->where('calendar_id', $calendar->id)
            ->firstOrFail();

        if (!$subCalendar->overlap) {
            $this->checkForOverlap($subCalendar->id, $data['start_date'], $data['end_date']);
        }

        $event = DB::transaction(function () use ($data, $subCalendar, $request, $calendar) {
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
                $this->syncCustomEventFieldValues($event, (int) $calendar->id, $incomingValues);
            }

            return $event;
        });

        $event->load([
            'customEventFieldValues.customEventField',
            'customEventFieldValues.customEventFieldOption',
        ]);

        return new EventResource($event);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Event $event)
    {
        $key = $request->header('X-Access-Key');
        $accessKey = AccessKey::where('key', $key)->firstorfail();

        //check if the access key has view permission
        $hasPermissionToView = false;
        if($accessKey->shared_type == 'selected_sub_calendars'){
            $subCalendarPermission = SubCalendarPermission::where('sub_calendar_id',$event->sub_calendar_id)->where('access_key_id',$accessKey->id)->first();
            $hasPermissionToView = $subCalendarPermission && in_array($subCalendarPermission->access_type,['read_only','modify']);
        }elseif ($accessKey->shared_type == 'all_sub_calendars' && in_array($accessKey->role,['read_only','modify'])) {
            $hasPermissionToView = true;
        }

        if(!$hasPermissionToView){
            abort(403,'You do not have permission to view this event');
        }


        $event->load([
            'customEventFieldValues.customEventField',
            'customEventFieldValues.customEventFieldOption',
        ]);

        return new EventResource($event);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        $key = $request->header('X-Access-Key');
        $accessKey = AccessKey::where('key', $key)->firstorfail();

        //check if the access key has modify permission
        $hasPermissionToUpdate = false;
        if($accessKey->shared_type == 'selected_sub_calendars'){
            $subCalendarPermission = SubCalendarPermission::where('sub_calendar_id',$event->sub_calendar_id)->where('access_key_id',$accessKey->id)->first();
            $hasPermissionToUpdate = $subCalendarPermission && $subCalendarPermission->access_type == 'modify';
        }elseif ($accessKey->shared_type == 'all_sub_calendars' && $accessKey->role == 'modify') {
            $hasPermissionToUpdate = true;
        }

        if(!$hasPermissionToUpdate){
            abort(403,'You do not have permission to update this event');
        }

        $calendar = $accessKey->calendar;

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

        DB::transaction(function () use ($event, $data, $request, $calendar) {
            if (array_key_exists('sub_calendar_id', $data)) {
                $subCalendar = SubCalendar::query()
                    ->where('id', $data['sub_calendar_id'])
                    ->where('calendar_id', $calendar->id)
                    ->firstOrFail();

                $data['sub_calendar_id'] = $subCalendar->id;
            } else {
                $subCalendar = $event->subCalendar;
            }

            if (!$subCalendar->overlap) {
                $startDate = $data['start_date'] ?? $event->start_date;
                $endDate = $data['end_date'] ?? $event->end_date;
                $this->checkForOverlap($subCalendar->id, $startDate, $endDate, $event->id);
            }

            $event->fill($data);
            $event->save();

            $incomingValues = $data['custom_event_field_values']
                ?? $request->input('customEventFieldValues')
                ?? null;

            if (is_array($incomingValues)) {
                $this->syncCustomEventFieldValues($event, (int) $calendar->id, $incomingValues);
            }
        });

        $event->load([
            'customEventFieldValues.customEventField',
            'customEventFieldValues.customEventFieldOption',
        ]);

        return new EventResource($event);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Event $event)
    {
        $key = $request->header('X-Access-Key');
        $accessKey = AccessKey::where('key', $key)->firstorfail();

        //check if the access key has modify permission
        $hasPermissionToDelete = false;
        if($accessKey->shared_type == 'selected_sub_calendars'){
            $subCalendarPermission = SubCalendarPermission::where('sub_calendar_id',$event->sub_calendar_id)->where('access_key_id',$accessKey->id)->first();
            $hasPermissionToDelete = $subCalendarPermission && $subCalendarPermission->access_type == 'modify';
        }elseif ($accessKey->shared_type == 'all_sub_calendars' && $accessKey->role == 'modify') {
            $hasPermissionToDelete = true;
        }

        if(!$hasPermissionToDelete){
            abort(403,'You do not have permission to delete this event');
        }
        $event->delete();

        return response()->json(null, 204);
    }

    /**
     * Check if the event overlaps with existing events in the same sub-calendar.
     *
     * @param int $subCalendarId
     * @param string $startDate
     * @param string $endDate
     * @param int|null $excludeEventId
     * @return void
     */
    private function checkForOverlap(int $subCalendarId, string $startDate, string $endDate, ?int $excludeEventId = null): void
    {
        $overlapExists = Event::query()
            ->where('sub_calendar_id', $subCalendarId)
            ->when($excludeEventId, fn($q) => $q->where('id', '!=', $excludeEventId))
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where('start_date', '<', $endDate)
                        ->where('end_date', '>', $startDate);
                });
            })
            ->exists();

        if ($overlapExists) {
            abort(response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'overlap' => ['The event overlaps with an existing event in this sub-calendar.'],
                ],
            ], 422));
        }
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
