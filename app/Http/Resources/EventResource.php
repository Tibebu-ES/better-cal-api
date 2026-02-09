<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Event $resource
 */
class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->resource;

        $customFields = [];

        if ($event->relationLoaded('customEventFieldValues')) {
            $grouped = $event->customEventFieldValues->groupBy('custom_event_field_id');

            foreach ($grouped as $fieldId => $rows) {
                $first = $rows->first();
                $field = $first?->customEventField;

                $type = $field?->type;

                $payload = [
                    'id' => (int) $fieldId,
                    'name' => $field?->name,
                    'type' => $type,
                ];

                if ($type === 'text') {
                    $payload['value'] = $first?->value;
                } elseif ($type === 's_select') {
                    $opt = $first?->customEventFieldOption;

                    $payload['option_id'] = $opt?->id;
                    $payload['option'] = $opt ? ['id' => $opt->id, 'name' => $opt->name] : null;
                } elseif ($type === 'm_select') {
                    $options = $rows
                        ->map(fn ($r) => $r->customEventFieldOption)
                        ->filter()
                        ->unique('id')
                        ->values();

                    $payload['option_ids'] = $options->pluck('id')->values()->all();
                    $payload['options'] = $options->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])->values()->all();
                } else {
                    // Unknown type (future-proof): still return something predictable.
                    $payload['raw'] = $rows->map(function ($r) {
                        return [
                            'id' => $r->id,
                            'value' => $r->value,
                            'custom_event_field_option_id' => $r->custom_event_field_option_id,
                        ];
                    })->values()->all();
                }

                // Dictionary keyed by field id (as a string in JSON, which is fine/normal).
                $customFields[(string) $fieldId] = $payload;
            }
        }

        return [
            'id' => $event->id,
            'sub_calendar_id' => $event->sub_calendar_id,
            'title' => $event->title,
            'all_day' => (bool) $event->all_day,
            'start_date' => $event->start_date,
            'end_date' => $event->end_date,
            'rrule' => $event->rrule,
            'about' => $event->about,
            'where' => $event->where,
            'who' => $event->who,
            'created_at' => $event->created_at,
            'updated_at' => $event->updated_at,

            // New grouped shape:
            'custom_event_fields' => $customFields,
        ];
    }
}
