<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    public static function generateUniqueAccessKey(): string
    {
        // 40 chars is plenty; loop in the extremely unlikely event of a collision.
        for ($i = 0; $i < 5; $i++) {
            $key = Str::random(40);

            $exists = AccessKey::query()
                ->where('key', $key)
                ->exists();

            if (!$exists) {
                return $key;
            }
        }

        // If we somehow collide repeatedly, make it longer.
        return Str::random(80);
    }
}
