<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewProfile extends Model
{
    protected $fillable = [
        'user_id',
        'bio',
        'skills',
        'experience_years',
        'availability',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
