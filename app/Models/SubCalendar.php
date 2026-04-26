<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCalendar extends Model
{
    use HasFactory;
    protected $fillable = [
        'calendar_id',
        'name',
        'active',
        'overlap',
        'color'
    ];

    protected $casts = [
        'active' => 'boolean',
        'overlap' => 'boolean'
    ];


    public function calendar(){
        return $this->belongsTo(Calendar::class);
    }

    public function events(){
        return $this->hasMany(Event::class);
    }

}
