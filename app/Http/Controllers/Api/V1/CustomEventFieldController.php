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
    public function index(Request $request, Calendar $calendar)
    {
        $user = $request->user();

        if ((int) $calendar->user_id !== (int) $user->id) {
            abort(404);
        }

        $query = $calendar->customEventFields()
            ->with(['options'])
            ->orderByDesc('id');

        $fields = $query->paginate((int) $request->integer('per_page', 15));

        return response()->json($fields);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Calendar $calendar)
    {
        $user = $request->user();

        if ((int) $calendar->user_id !== (int) $user->id) {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['text', 's_select', 'm_select'])],
            'options' => ['sometimes', 'array'],
            'options.*' => ['required', 'string', 'max:255'],
        ]);

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
    public function show(Request $request, Calendar $calendar, CustomEventField $customEventField)
    {
        $user = $request->user();

        if ((int) $calendar->user_id !== (int) $user->id) {
            abort(404);
        }

        $customEventField->load(['options']);

        return response()->json($customEventField);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Calendar $calendar, CustomEventField $customEventField)
    {
        $user = $request->user();

        if ((int) $calendar->user_id !== (int) $user->id) {
            abort(404);
        }

        $customEventField->load(['options']);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(['text', 's_select', 'm_select'])],

            // options are objects now to preserve ids:
            'options' => ['sometimes', 'array'],
            'options.*.id' => ['sometimes', 'integer'],
            'options.*.name' => ['required', 'string', 'max:255'],
        ]);

        $newType = $data['type'] ?? $customEventField->type;

        if (
            in_array($newType, ['s_select', 'm_select'], true)
            && array_key_exists('type', $data)
            && !array_key_exists('options', $data)
        ) {
            return response()->json([
                'message' => 'Validation error.',
                'errors' => [
                    'options' => ['Options are required when changing type to a select field type.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($customEventField, $data, $newType) {
            $customEventField->fill($data);
            $customEventField->save();

            // Only manage options for select types.
            if (!in_array($newType, ['s_select', 'm_select'], true)) {
                return;
            }

            if (!array_key_exists('options', $data)) {
                return;
            }

            $existingIds = $customEventField->options()->pluck('id')->all();

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
                        ->where('custom_event_field_id', $customEventField->id)
                        ->update(['name' => $name]);

                    continue;
                }

                // Create new option (new id is fine because nothing references it yet).
                CustomEventFieldOption::create([
                    'custom_event_field_id' => $customEventField->id,
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
                ->where('custom_event_field_id', $customEventField->id)
                ->whereIn('id', $toDelete)
                ->delete();
        });

        $customEventField->load('options');

        return response()->json($customEventField);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Calendar $calendar, CustomEventField $customEventField)
    {
        $user = $request->user();

        if ((int) $calendar->user_id !== (int) $user->id) {
            abort(404);
        }

        $customEventField->delete();

        return response()->json(null, 204);
    }
}
