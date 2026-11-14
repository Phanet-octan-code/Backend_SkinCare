<?php

namespace Database\Seeders;

use App\Models\Bundle;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class KhmerTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Categories
        $categoryTranslations = [
            'Cleanser' => [
                'name_km' => 'ផលិតផលលាងសម្អាតមុខ',
                'description_km' => 'រូបមន្តលាងសម្អាតមុខបែបធម្មជាតិ ថ្នមស្បែក និងរក្សាសំណើមបានយ៉ាងល្អ។',
            ],
            'Toner' => [
                'name_km' => 'ទឹកជូតស្បែកមុខ (តូណា)',
                'description_km' => 'ជួយសម្រួលតុល្យភាព pH និងផ្ដល់សំណើមជ្រៅដល់កោសិកាស្បែក។',
            ],
            'Serum' => [
                'name_km' => 'សេរ៉ូមបំប៉នស្បែកស៊ីជម្រៅ',
                'description_km' => 'សារធាតុចំរាញ់រុក្ខជាតិកំហាប់ខ្ពស់ ជួយបំប៉ន និងព្យាបាលបញ្ហាស្បែកចំគោលដៅ។',
            ],
            'Moisturizer' => [
                'name_km' => 'ក្រែមផ្ដល់សំណើមស្បែកមុខ',
                'description_km' => 'ការពាររបាំងស្បែក និងចាក់សោសំណើមបានយូរអង្វែងពេញមួយថ្ងៃ។',
            ],
            'Sunscreen' => [
                'name_km' => 'ឡេការពារកម្ដៅថ្ងៃ',
                'description_km' => 'ការពារកាំរស្មី UV យ៉ាងមានប្រសិទ្ធភាពដោយមិនកកស្ទះរន្ធញើស។',
            ],
            'Eye Care' => [
                'name_km' => 'ផលិតផលថែរក្សាជុំវិញភ្នែក',
                'description_km' => 'កាត់បន្ថយភាពហើម ស្នាមជ្រីវជ្រួញ និងរង្វង់ខ្មៅក្រោមភ្នែក។',
            ],
            'Mask' => [
                'name_km' => 'ម៉ាសបិទមុខបំប៉ន',
                'description_km' => 'ព្យាបាលស្បែកមុខបន្ទាន់ ជួយឱ្យស្បែកភ្លឺថ្លា និងទន់រលោង។',
            ],
            'Exfoliator' => [
                'name_km' => 'ផលិតផលជម្រុះកោសិកាចាស់ៗ',
                'description_km' => 'ជម្រុះកោសិកាងាប់ៗដោយសុវត្ថិភាព ផ្ដល់ភាពស្រស់ថ្លាឡើងវិញ។',
            ],
        ];

        foreach ($categoryTranslations as $enName => $trans) {
            Category::where('name', 'LIKE', "%{$enName}%")->update($trans);
        }

        // 2. Sample Products Khmer Translations
        $products = Product::all();
        foreach ($products as $prod) {
            $name = $prod->name;
            $nameKm = null;
            $descKm = null;

            if (stripos($name, 'Vitamin C') !== false || stripos($name, 'Radiance') !== false) {
                $nameKm = 'សេរ៉ូមវីតាមីន C ជំនួយស្បែកភ្លឺថ្លាធម្មជាតិ';
                $descKm = 'រូបមន្តវីតាមីន C និងរុក្ខសាស្ត្រធម្មជាតិ ជួយកាត់បន្ថយស្នាមអុជខ្មៅ និងផ្ដល់ពន្លឺភ្លឺថ្លាដល់ផ្ទៃមុខ។';
            } elseif (stripos($name, 'Squalane') !== false || stripos($name, 'Barrier') !== false) {
                $nameKm = 'ក្រែមស្តាររបាំងការពារស្បែក Squalane';
                $descKm = 'ផ្សំឡើងពី Squalane បរិសុទ្ធ ជួយពង្រឹងកោសិកាស្បែក និងរក្សាសំណើមយូរអង្វែង។';
            } elseif (stripos($name, 'Essence') !== false || stripos($name, 'Toner') !== false) {
                $nameKm = 'ទឹកតូណាផ្កាឱសថផ្ដល់សំណើមជ្រៅ';
                $descKm = 'ចំរាញ់ចេញពីផ្កាឱសថធម្មជាតិ ជួយឱ្យស្បែកមានសំណើម ត្រជាក់ស្រួល និងស្រស់ស្រាយ។';
            } elseif (stripos($name, 'Cleanser') !== false || stripos($name, 'Wash') !== false) {
                $nameKm = 'ជែលលាងសម្អាតមុខ botanical ទន់ភ្លន់';
                $descKm = 'លាងសម្អាតជាតិកខ្វក់ និងធូលីយ៉ាងស្អាត ដោយមិនធ្វើឱ្យស្បែកស្ងួតតឹង។';
            } elseif (stripos($name, 'Sun') !== false || stripos($name, 'SPF') !== false) {
                $nameKm = 'ឡេការពារកម្ដៅថ្ងៃរូបមន្តទឹក mineral SPF 50+';
                $descKm = 'ការពារស្បែកពីកម្ដៅថ្ងៃ និងពន្លឺទូរសព្ទ/កុំព្យូទ័រ ស្រាលស្រទន់មិនស្អិត។';
            } else {
                $nameKm = 'រូបមន្តថែរក្សាស្បែក ' . $name;
                $descKm = 'ផលិតផលថែរក្សាស្បែកតាមស្តង់ដារគុណភាពខ្ពស់ ផ្ដល់សុខភាពល្អដល់ស្បែករបស់អ្នក។';
            }

            $prod->update([
                'name_km' => $nameKm,
                'description_km' => $descKm,
            ]);
        }

        // 3. Bundles
        $bundles = Bundle::all();
        foreach ($bundles as $bundle) {
            $bName = $bundle->name;
            $bNameKm = 'ឈុតថែរក្សាស្បែក ' . $bName;
            $bDescKm = 'ឈុតផលិតផលផ្សំឡើងយ៉ាងស៊ីសង្វាក់គ្នា ផ្ដល់នូវប្រសិទ្ធភាពទ្វេដង និងសន្សំសំចៃខ្ពស់។';

            if (stripos($bName, 'Glow') !== false || stripos($bName, 'Radiance') !== false) {
                $bNameKm = 'ឈុតស្បែកភ្លឺថ្លា Glow Radiance';
                $bDescKm = 'ការរួមបញ្ចូលគ្នារវាងសេរ៉ូមវីតាមីន C និងក្រែមផ្ដល់សំណើម ដើម្បីភាពស្រស់ថ្លាពេញលេញ។';
            } elseif (stripos($bName, 'Hydration') !== false || stripos($bName, 'Ritual') !== false) {
                $bNameKm = 'ឈុតផ្ដល់សំណើម Hydration Ritual';
                $bDescKm = 'ការថែទាំសំណើម ៣ ដំណាក់កាល សម្រាប់ស្បែកស្ងួត ឬខ្វះជាតិទឹក។';
            }

            $bundle->update([
                'name_km' => $bNameKm,
                'description_km' => $bDescKm,
            ]);
        }
    }
}

