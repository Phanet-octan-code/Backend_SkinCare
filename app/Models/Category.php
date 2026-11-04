<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('storefront_categories');
            \Illuminate\Support\Facades\Cache::forget('storefront_stats');
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('storefront_categories');
            \Illuminate\Support\Facades\Cache::forget('storefront_stats');
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the multilingual localized name of the category.
     */
    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();
        if ($locale === 'en') {
            return $this->name;
        }

        $map = [
            'Cleansers & Washes' => [
                'km' => 'ទឹកសម្អាត & ជែលលាងមុខ',
                'zh' => '洁面与卸妆',
            ],
            'Toners & Essences' => [
                'km' => 'ទឹកតប់ & អេសិន្ស',
                'zh' => '爽肤水与精华水',
            ],
            'Serums & Ampoules' => [
                'km' => 'សេរ៉ូម & អំពូល',
                'zh' => '精华液与安瓶',
            ],
            'Moisturizers & Creams' => [
                'km' => 'ក្រែមផ្តល់សំណើម',
                'zh' => '保湿霜与面霜',
            ],
            'Sunscreens & UV Shields' => [
                'km' => 'ឡេការពារកម្តៅថ្ងៃ',
                'zh' => '防晒与隔离',
            ],
            'Exfoliants & Peels' => [
                'km' => 'ផលិតផលជម្រុះកោសិកា',
                'zh' => '去角质与果酸',
            ],
            'Eye & Lip Care' => [
                'km' => 'ថែរក្សាភ្នែក & បបូរមាត់',
                'zh' => '眼部与唇部护理',
            ],
            'Masks & Treatments' => [
                'km' => 'ម៉ាស & ការព្យាបាលស្បែក',
                'zh' => '面膜与特殊护理',
            ],
            'SkinCare' => [
                'km' => 'ផលិតផលថែរក្សាស្បែក',
                'zh' => '护肤保养',
            ],
            'Bulk Test Category' => [
                'km' => 'ប្រភេទទំនិញសាកល្បង',
                'zh' => '批量测试分类',
            ],
        ];

        return $map[$this->name][$locale] ?? $this->name;
    }
}
