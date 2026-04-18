<?php

namespace Database\Factories;

use App\Models\SubCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sub_calendar_id' => SubCalendar::factory(),
            'title' => fake()->sentence(3),
            'all_day' => fake()->boolean(),
            'start_date' => fake()->unique()->dateTimeBetween('now', '+20 days'),
            'end_date' => function (array $attributes) {
                return $attributes['start_date']->modify('+' . fake()->numberBetween(1, 8) . ' hours');
            },
        ];
    }
}
