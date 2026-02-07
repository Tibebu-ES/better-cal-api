<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    //
    protected $fillable = [
        'sub_calendar_id',
        'title',
        'all_day',
        'start_date',
        'end_date',
        'rrule',
        'about',
        'where',
        'who'
    ];

    public function subCalendar(){
        return $this->belongsTo(SubCalendar::class);
    }
}
