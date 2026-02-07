<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessKey extends Model
{
    protected $fillable = [
        'calendar_id',
        'name',
        'key',
        'active',
        'has_password',
        'password'
    ];

    public function calendar(){
        return $this->belongsTo(Calendar::class);
    }

    public function subCalendarPermissions(){
        return $this->hasMany(SubCalendarPermission::class);
    }
}
