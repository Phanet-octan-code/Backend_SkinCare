<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'position_id',
        'phone',
        'email',
        'start_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getTenureAttribute(): string
    {
        if (!$this->start_date) {
            return '—';
        }

        $now = now();
        $diffYears = $this->start_date->diffInYears($now);
        $diffMonths = $this->start_date->copy()->addYears($diffYears)->diffInMonths($now);

        if ($diffYears > 0) {
            return "{$diffYears} yr" . ($diffYears > 1 ? 's ' : ' ') . ($diffMonths > 0 ? "{$diffMonths} mo" . ($diffMonths > 1 ? 's' : '') : '');
        }

        if ($diffMonths > 0) {
            return "{$diffMonths} mo" . ($diffMonths > 1 ? 's' : '');
        }

        $diffDays = $this->start_date->diffInDays($now);
        return "{$diffDays} day" . ($diffDays === 1 ? '' : 's');
    }
}
