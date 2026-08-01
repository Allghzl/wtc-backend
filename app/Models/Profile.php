<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profile extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'puid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'puid',
        'study_class_id',
        'nickname',
        'points',
        'display_name',
        'avatar_key',
        'email',
        'last_login_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'profile_roles',
            'profile_puid',
            'role_id',
            'puid',
            'id',
        );
    }
}
