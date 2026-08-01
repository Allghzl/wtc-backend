<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile_role extends Model
{
    public function roles()
    {
        return $this->hasMany(Role::class);
    }
}
