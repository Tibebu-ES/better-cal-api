<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomEventFieldOption extends Model
{
    //
    protected $fillable = [
        'custom_event_field_id',
        'name'
    ];

    public function customEventField(){
        return $this->belongsTo(CustomEventField::class);
    }
}
