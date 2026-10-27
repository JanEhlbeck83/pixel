<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    public const FAILED = 'failed';

    protected $fillable = [
        'uuid', 'user_id', 'product', 'provider', 'provider_reference', 'status',
        'amount_cents', 'currency', 'meta', 'paid_at', 'fulfilled_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function title(): string
    {
        $product = config('payments.products.'.$this->product, []);

        return match ($product['type'] ?? null) {
            'extend' => 'Pixelwand nach '.(($product['axis'] ?? 'x') === 'x' ? 'rechts' : 'unten').' erweitern (+'.($product['amount'] ?? 0).' px)',
            'pixels' => ($product['amount'] ?? 0).' Extra-Pixel',
            default => $this->product,
        };
    }

    public function priceFormatted(): string
    {
        return number_format($this->amount_cents / 100, 2, ',', '.').' €';
    }
}
