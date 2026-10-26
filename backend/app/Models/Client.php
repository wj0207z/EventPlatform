<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

}
