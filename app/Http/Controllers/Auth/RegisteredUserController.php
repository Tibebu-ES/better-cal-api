<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccessKey;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->string('password')),
        ]);

        event(new Registered($user));

        //create default calendar
        $user->calendars()->create([
            'name' => 'Test calendar',
            'active' => true,
            'about' => 'Your first calendar!',
            'timezone' => 'UTC',
            'locale' => 'en'
        ]);
        $defaultCalendar = $user->calendars()->first();
        //add test sub-calendars, Personal, Work and Social
        $defaultCalendar->subCalendars()->create([
            'name' => 'Personal'
        ]);
        $defaultCalendar->subCalendars()->create([
            'name' => 'Work',
            'color' => '#FA003F'
        ]);
        $defaultCalendar->subCalendars()->create([
            'name' => 'Social',
            'color' => '#a60cc4'
        ]);

        //create a default access key with modify access to the default calendar
        $defaultCalendar->accessKeys()->create([
            'name' => 'Admin',
            'active' => true,
            'key' => AccessKey::generateUniqueAccessKey(),
            'shared_type' => 'all_sub_calendars',
            'role' => 'modify'
        ]);


        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'default_calendar' => $defaultCalendar,
            'token_type' => 'Bearer'
        ]);
    }
}
