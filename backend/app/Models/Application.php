<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    protected $fillable = [
        'job_posting_id',
        'crew_id',
        'reviewed_by',
        'status',
        'notes',
    ];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

        public function crew(): BelongsTo
    {
        return $this->belongsTo(User::class, 'crew_id');
    }
    
        public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

}
