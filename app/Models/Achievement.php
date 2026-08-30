<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    protected $fillable = [
        'name',
        'description',
        'trigger_type',
        'trigger_config',
        'points_reward',
        'is_active',
        'badge_emoji',
    ];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'points_reward'  => 'integer',
            'is_active'      => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(
            Profile::class,
            'profile_achievements',
            'achievement_id',
            'profile_id',
        )->withPivot(['is_pinned', 'awarded_at'])->withTimestamps();
    }
}
