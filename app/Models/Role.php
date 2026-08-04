<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name'
    ];

    public function profile_role()
    {
        return $this->belongsTo(Profile_role::class);
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(
            Profile::class,
            'profile_roles',
            'role_id',
            'profile_id',
            'id',
            'id',
        );
    }
}
