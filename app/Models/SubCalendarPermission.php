<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubCalendarPermission extends Model
{
    //
    protected $fillable = [
        'sub_calendar_id',
        'access_key_id',
        'access_type'
    ];

    public function subCalendar(){
        return $this->belongsTo(SubCalendar::class);
    }

    public function accessKey(){
        return $this->belongsTo(AccessKey::class);
    }
}
