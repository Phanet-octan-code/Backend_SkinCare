<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransaction extends Model
{
    use HasFactory;

    // Movement types
    public const TYPE_STOCK_IN = 'stock-in';
    public const TYPE_STOCK_OUT = 'stock-out';
    public const TYPE_SALE = 'sale';
    public const TYPE_RETURN = 'return';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_DAMAGED = 'damaged';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'product_id',
        'user_id',
        'supplier_id',
        'type',
        'qty',
        'reference_number',
        'notes',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
