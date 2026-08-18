<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosting extends Model
{
    protected $fillable = [
        'event_id',
        'recruiter_id',
        'title',
        'description',
        'crew_type',
        'number_of_positions',
        'pay_rate',
        'status',
    ];

    protected $casts = [
        'pay_rate' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(
            Application::class,
            'job_posting_id'
        );
    }
}
