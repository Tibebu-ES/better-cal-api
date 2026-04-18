<?php

namespace Database\Factories;

use App\Models\Calendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubCalendar>
 */
class SubCalendarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'calendar_id' => Calendar::factory(),
            'name' => fake()->name(),
            'active' => fake()->boolean(),
            'overlap' => fake()->boolean(),
            'color' => fake()->hexColor(),

        ];
    }
}
