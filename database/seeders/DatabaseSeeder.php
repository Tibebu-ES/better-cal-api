<?php

namespace Database\Seeders;

use App\Models\AccessKey;
use App\Models\SubCalendar;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('377740art'),
        ]);

        //add a calendar
        $calendar = $testUser->calendars()->create([
            'name' => 'Test calendar',
            'active' => true,
            'about' => 'Your first calendar!',
            'timezone' => 'UTC',
            'locale' => 'en'
        ]);

        //add key
        $calendar->accessKeys()->create([
            'name' => 'Admin',
            'active' => true,
            'key' => AccessKey::generateUniqueAccessKey(),
            'shared_type' => 'all_sub_calendars',
            'role' => 'modify'
        ]);

        //add test sub-calendars, Personal, Work and Social - each with 10 events
        $personal = SubCalendar::factory()
            ->hasEvents(20)
            ->create([
                'name' => 'Personal',
                'calendar_id' => $calendar->id,
                'color' => '#000000'
            ]);
        $work = SubCalendar::factory()
            ->hasEvents(20)
            ->create([
                'name' => 'Work',
                'calendar_id' => $calendar->id,
                'color' => '#FA003F'
            ]);
        $social = SubCalendar::factory()
            ->hasEvents(20)
            ->create([
                'name' => 'Social',
                'calendar_id' => $calendar->id,
                'color' => '#a60cc4'
            ]);


    }
}
