<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Client extends Model
{

    use HasFactory;
    protected $fillable = [
        'name',
        'email',
        'phone',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_client')
            ->withTimestamps();
    }

}
