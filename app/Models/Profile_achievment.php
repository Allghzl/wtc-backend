<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile_achievment extends Model
{

    public function achievments()
    {
        return $this->hasMany(Achievment::class);
    }
}
