<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoClaim extends Model
{
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'promo_code_id',
        'code',
        'amount_minor',
        'status',
        'rejection_reason',
        'claimed_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'claimed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
