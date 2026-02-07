<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomEventField extends Model
{
    //
    protected $fillable = [
        'calendar_id',
        'name',
        'type'
    ];

    public function calendar(){
        return $this->belongsTo(Calendar::class);
    }

    public function options(){
        return $this->hasMany(CustomEventFieldOption::class);
    }


}
