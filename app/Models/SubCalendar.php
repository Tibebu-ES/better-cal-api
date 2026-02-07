<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubCalendar extends Model
{
    protected $fillable = [
        'calendar_id',
        'name',
        'active',
        'overlap',
        'color'
    ];

    public function calendar(){
        return $this->belongsTo(Calendar::class);
    }

    public function events(){
        return $this->hasMany(Event::class);
    }

}
