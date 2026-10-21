<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'source_location',
        'destination_location',
        'status',
        'user_id',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(StockTransferDetail::class, 'transfer_id');
    }

    public static function generateReferenceNumber(): string
    {
        $prefix = 'TRF-' . date('Ymd') . '-';
        $latest = static::where('reference_number', 'LIKE', $prefix . '%')
            ->orderByDesc('id')
            ->value('reference_number');

        if ($latest) {
            $sequence = (int) substr($latest, strlen($prefix)) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
