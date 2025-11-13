<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'type',
        'provider_id',
        'external_id',
        'metadata',
        'is_default',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getDisplayName(): string
    {
        $metadata = $this->metadata ?? [];
        
        return match($this->type) {
            'card' => sprintf('**** %s (%s)', $metadata['last4'] ?? '****', $metadata['brand'] ?? 'Card'),
            'bank_account' => sprintf('Bank ****%s', $metadata['last4'] ?? '****'),
            'paypal_account' => $metadata['email'] ?? 'PayPal Account',
            default => $this->type,
        };
    }
}
