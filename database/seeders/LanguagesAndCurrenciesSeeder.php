<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use Illuminate\Database\Seeder;

class LanguagesAndCurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_rtl' => false, 'is_default' => true, 'sort' => 1],
            ['code' => 'tr', 'name' => 'Turkish', 'native_name' => 'Türkçe', 'is_rtl' => false, 'is_default' => false, 'sort' => 2],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'is_rtl' => true, 'is_default' => false, 'sort' => 3],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(['code' => $language['code']], $language + ['is_active' => true]);
        }

        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'symbol_position' => 'before', 'decimals' => 2, 'is_default' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'symbol_position' => 'before', 'decimals' => 2, 'is_default' => false],
            ['code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺', 'symbol_position' => 'after', 'decimals' => 2, 'decimal_separator' => ',', 'thousands_separator' => '.', 'is_default' => false],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(['code' => $currency['code']], $currency + ['is_active' => true]);
        }
    }
}
