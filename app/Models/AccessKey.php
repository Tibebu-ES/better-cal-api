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
        'password',
        'shared_type',
        'role'
    ];

    protected $casts = [
        'active' => 'boolean',
        'has_password' => 'boolean'
    ];

    public function calendar(){
        return $this->belongsTo(Calendar::class);
    }

    public function subCalendarPermissions(){
        return $this->hasMany(SubCalendarPermission::class);
    }
}
