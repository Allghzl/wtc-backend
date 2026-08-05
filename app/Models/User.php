<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Session;
use Laravel\Sanctum\HasApiTokens;


#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory,
        HasApiTokens,
        Notifiable,
        HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'puid',
        'name',
        'email',
        'password',
        'provider',
        'avatar',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'      => 'datetime',
            'password'           => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function sessions()
    {
        return $this->hasMany(Session::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isLocal(): bool
    {
        return $this->provider === 'local';
    }

    public function isPinat(): bool
    {
        return $this->provider === 'pinat';
    }

    public function hasRole(string $roleName): bool
    {
        return $this->profile()
            ->whereHas('roles', fn ($query) => $query->where('name', $roleName))
            ->exists();
    }
}
