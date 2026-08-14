<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'amount_minor',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function claims(): HasMany
    {
        return $this->hasMany(PromoClaim::class);
    }
}
