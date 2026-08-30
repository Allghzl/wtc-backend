<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'profile_id',
        'track_id',
        'certificate_number',
        'grade',
        'grade_score',
        'status',
        'issued_at',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'grade_score' => 'float',
            'issued_at'   => 'datetime',
        ];
    }

    /**
     * Auto-generate UUIDs for both id and certificate_number on creation.
     */
    public function uniqueIds(): array
    {
        return ['id', 'certificate_number'];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Grade Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Convert a numeric score (0-100) to a letter grade.
     */
    public static function gradeLabel(float $score): string
    {
        return match (true) {
            $score >= 95 => 'A+',
            $score >= 90 => 'A',
            $score >= 80 => 'B+',
            $score >= 70 => 'B',
            $score >= 65 => 'C+',
            $score >= 60 => 'C',
            default      => 'D',
        };
    }
}
