<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $fillable = [
        'name',
        'description'
    ];

    public function profile_achievment()
    {
        return $this->belongsTo(Profile_achievment::class);
    }
}
