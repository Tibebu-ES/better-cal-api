<?php

namespace Tests\Feature;

use App\Models\AccessKey;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\SubCalendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOverlapTest extends TestCase
{
    use RefreshDatabase;

    protected $calendar;
    protected $subCalendar;
    protected $accessKey;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->calendar = Calendar::factory()->create(['user_id' => $user->id]);
        $this->subCalendar = SubCalendar::factory()->create([
            'calendar_id' => $this->calendar->id,
            'name' => 'Test SubCalendar',
            'overlap' => false,
            'active' => true,
        ]);
        $this->accessKey = AccessKey::create([
            'calendar_id' => $this->calendar->id,
            'name' => 'Test Key',
            'key' => 'test-key',
            'shared_type' => 'all_sub_calendars',
            'role' => 'modify',
        ]);
    }

    public function test_it_prevents_overlapping_events_when_overlap_is_false_on_store()
    {
        // Create an existing event
        Event::create([
            'sub_calendar_id' => $this->subCalendar->id,
            'title' => 'Existing Event',
            'start_date' => '2026-04-26 10:00:00',
            'end_date' => '2026-04-26 11:00:00',
        ]);

        // Try to create an overlapping event
        $response = $this->withHeader('X-Access-Key', 'test-key')
            ->postJson('/api/v1/events', [
                'sub_calendar_id' => $this->subCalendar->id,
                'title' => 'Overlapping Event',
                'start_date' => '2026-04-26 10:30:00',
                'end_date' => '2026-04-26 11:30:00',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['overlap']);
    }

    public function test_it_prevents_overlapping_events_when_overlap_is_false_on_update()
    {
        // Create two non-overlapping events
        Event::create([
            'sub_calendar_id' => $this->subCalendar->id,
            'title' => 'Event 1',
            'start_date' => '2026-04-26 10:00:00',
            'end_date' => '2026-04-26 11:00:00',
        ]);

        $event2 = Event::create([
            'sub_calendar_id' => $this->subCalendar->id,
            'title' => 'Event 2',
            'start_date' => '2026-04-26 12:00:00',
            'end_date' => '2026-04-26 13:00:00',
        ]);

        // Try to update Event 2 to overlap with Event 1
        $response = $this->withHeader('X-Access-Key', 'test-key')
            ->patchJson("/api/v1/events/{$event2->id}", [
                'start_date' => '2026-04-26 10:30:00',
                'end_date' => '2026-04-26 11:30:00',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['overlap']);
    }

    public function test_it_allows_overlapping_events_when_overlap_is_true()
    {
        $this->subCalendar->update(['overlap' => true]);

        Event::create([
            'sub_calendar_id' => $this->subCalendar->id,
            'title' => 'Existing Event',
            'start_date' => '2026-04-26 10:00:00',
            'end_date' => '2026-04-26 11:00:00',
        ]);

        $response = $this->withHeader('X-Access-Key', 'test-key')
            ->postJson('/api/v1/events', [
                'sub_calendar_id' => $this->subCalendar->id,
                'title' => 'Overlapping Event',
                'start_date' => '2026-04-26 10:30:00',
                'end_date' => '2026-04-26 11:30:00',
            ]);

        $response->assertStatus(201);
    }
}
