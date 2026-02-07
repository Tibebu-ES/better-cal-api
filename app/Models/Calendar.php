<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Calendar extends Model
{
    //fillable
    protected $fillable = [
        'user_id',
        'name',
        'active',
        'about',
        'timezone',
        'locale'

    ];

    /**
     * Get the user that owns the calendar.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }


    /**
     * get the sub calendars
     */
    public function subCalendars()
    {
        return $this->hasMany(SubCalendar::class);
    }

    public function events(){
        return $this->hasManyThrough(Event::class, SubCalendar::class);
    }

    public function customEventFields(){
        return $this->hasMany(CustomEventField::class);
    }

    public function accessKeys(){
        return $this->hasMany(AccessKey::class);
    }

}
