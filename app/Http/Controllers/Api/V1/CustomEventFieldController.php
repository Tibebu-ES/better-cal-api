<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\CustomEventField;
use App\Models\CustomEventFieldOption;
use App\Models\CustomEventFieldValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomEventFieldController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = CustomEventField::query()
            ->with(['options'])
            ->whereHas('calendar', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orderByDesc('id');

        if ($request->filled('calendar_id')) {
            $query->where('calendar_id', (int) $request->input('calendar_id'));
        }

        $fields = $query->paginate((int) $request->integer('per_page', 15));

        return response()->json($fields);
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
            'type' => ['required', Rule::in(['text', 's_select', 'm_select'])],
            'options' => ['sometimes', 'array'],
            'options.*' => ['required', 'string', 'max:255'],
        ]);

        $calendar = Calendar::query()
            ->where('id', $data['calendar_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (in_array($data['type'], ['s_select', 'm_select'], true) && empty($data['options'])) {
            return response()->json([
                'message' => 'Validation error.',
                'errors' => [
                    'options' => ['Options are required for select field types.'],
                ],
            ], 422);
        }

        $field = DB::transaction(function () use ($calendar, $data) {
            $field = CustomEventField::create([
                'calendar_id' => $calendar->id,
                'name' => $data['name'],
                'type' => $data['type'],
            ]);

            if (in_array($data['type'], ['s_select', 'm_select'], true)) {
                $options = array_values(array_unique(array_map('trim', $data['options'] ?? [])));
                foreach ($options as $optionName) {
                    if ($optionName === '') {
                        continue;
                    }
                    CustomEventFieldOption::create([
                        'custom_event_field_id' => $field->id,
                        'name' => $optionName,
                    ]);
                }
            }

            return $field;
        });

        $field->load('options');

        return response()->json($field, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $field = CustomEventField::query()
            ->with(['options'])
            ->where('id', $id)
            ->whereHas('calendar', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        return response()->json($field);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $field = CustomEventField::query()
            ->with(['options'])
            ->where('id', $id)
            ->whereHas('calendar', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        $data = $request->validate([
            'calendar_id' => ['sometimes', 'integer'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(['text', 's_select', 'm_select'])],

            // options are objects now to preserve ids:
            'options' => ['sometimes', 'array'],
            'options.*.id' => ['sometimes', 'integer'],
            'options.*.name' => ['required', 'string', 'max:255'],
        ]);

        if (array_key_exists('calendar_id', $data)) {
            $calendar = Calendar::query()
                ->where('id', $data['calendar_id'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $data['calendar_id'] = $calendar->id;
        }

       /* // Prevent changing type if there are existing values (avoids reinterpreting stored data).
        if (array_key_exists('type', $data) && $data['type'] !== $field->type) {
            $hasValues = CustomEventFieldValue::query()
                ->where('custom_event_field_id', $field->id)
                ->exists();

            if ($hasValues) {
                return response()->json([
                    'message' => 'Validation error.',
                    'errors' => [
                        'type' => ['Cannot change field type while it has existing values.'],
                    ],
                ], 422);
            }
        }*/

        $newType = $data['type'] ?? $field->type;

        if (in_array($newType, ['s_select', 'm_select'], true) && array_key_exists('type', $data) && !array_key_exists('options', $data)) {
            return response()->json([
                'message' => 'Validation error.',
                'errors' => [
                    'options' => ['Options are required when changing type to a select field type.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($field, $data, $newType) {
            $field->fill($data);
            $field->save();

            // Only manage options for select types.
            if (!in_array($newType, ['s_select', 'm_select'], true)) {
                return;
            }

            if (!array_key_exists('options', $data)) {
                return;
            }

            $existingIds = $field->options()->pluck('id')->all();

            $incoming = $data['options'] ?? [];
            $incomingIds = [];

            foreach ($incoming as $opt) {
                $name = trim((string) ($opt['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                if (!empty($opt['id'])) {
                    $optionId = (int) $opt['id'];
                    $incomingIds[] = $optionId;

                    // Update only if it belongs to this field.
                    CustomEventFieldOption::query()
                        ->where('id', $optionId)
                        ->where('custom_event_field_id', $field->id)
                        ->update(['name' => $name]);

                    continue;
                }

                // Create new option (new id is fine because nothing references it yet).
                CustomEventFieldOption::create([
                    'custom_event_field_id' => $field->id,
                    'name' => $name,
                ]);
            }

            $incomingIds = array_values(array_unique($incomingIds));

            // Deletions: options omitted from payload.
            $toDelete = array_values(array_diff($existingIds, $incomingIds));
            if ($toDelete === []) {
                return;
            }


            CustomEventFieldOption::query()
                ->where('custom_event_field_id', $field->id)
                ->whereIn('id', $toDelete)
                ->delete();
        });

        // Convert the internal runtime exception into a 422 (without leaking stack traces).
        // (We keep this outside the transaction scope in case the exception was thrown.)
        try {
            // no-op: transaction already executed
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'Validation error.',
                'errors' => [
                    'options' => [$e->getMessage()],
                ],
            ], 422);
        }

        $field->load('options');

        return response()->json($field);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $field = CustomEventField::query()
            ->where('id', $id)
            ->whereHas('calendar', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        $field->delete();

        return response()->json(null, 204);
    }
}
