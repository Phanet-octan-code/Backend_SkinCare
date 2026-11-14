<?php

namespace Database\Seeders;

use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

class ShippingZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'code' => 'phnom_penh',
                'name_en' => 'Phnom Penh Urban Express',
                'name_km' => 'រាជធានីភ្នំពេញ (ដឹកជញ្ជូនរហ័ស)',
                'standard_fee' => 1.50,
                'free_shipping_threshold' => 40.00,
                'estimated_delivery_en' => '1-2 Business Days',
                'estimated_delivery_km' => '១-២ ថ្ងៃធ្វើការ',
                'provinces' => ['Phnom Penh', 'ភ្នំពេញ'],
                'is_active' => true,
            ],
            [
                'code' => 'provinces',
                'name_en' => 'Provincial Express Delivery',
                'name_km' => 'តាមបណ្ដាខេត្តទូទាំងប្រទេស',
                'standard_fee' => 3.00,
                'free_shipping_threshold' => 60.00,
                'estimated_delivery_en' => '2-4 Business Days',
                'estimated_delivery_km' => '២-៤ ថ្ងៃធ្វើការ',
                'provinces' => [
                    'Siem Reap', 'សៀមរាប',
                    'Battambang', 'បាត់ដំបង',
                    'Sihanoukville', 'ព្រះសីហនុ',
                    'Kampot', 'កំពត',
                    'Kandal', 'កណ្តាល',
                    'Takeo', 'តាកែវ',
                    'Kampong Cham', 'កំពង់ចាម',
                    'Pursat', 'ពោធិ៍សាត់',
                    'Kep', 'កែប',
                    'Koh Kong', 'កោះកុង',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'vip_express',
                'name_en' => 'VIP Same-Day Dispatch (Phnom Penh)',
                'name_km' => 'សេវាពិសេសក្នុងថ្ងៃតែមួយ (ភ្នំពេញ)',
                'standard_fee' => 4.50,
                'free_shipping_threshold' => 999.00, // VIP always paid courier
                'estimated_delivery_en' => 'Within 3-4 Hours',
                'estimated_delivery_km' => 'ក្នុងរយៈពេល ៣-៤ ម៉ោង',
                'provinces' => ['Phnom Penh', 'ភ្នំពេញ'],
                'is_active' => true,
            ],
        ];

        foreach ($zones as $zone) {
            ShippingZone::updateOrCreate(
                ['code' => $zone['code']],
                $zone
            );
        }
    }
}

