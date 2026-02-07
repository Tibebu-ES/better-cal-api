<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomEventFieldValue extends Model
{
    //
    protected $fillable = [
        'event_id',
        'custom_event_field_id',
        'value'
    ];

    public function event(){
        return $this->belongsTo(Event::class);
    }
    public function customEventField(){
        return $this->belongsTo(CustomEventField::class);
    }
}
