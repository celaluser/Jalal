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
            ['code' => 'az', 'name' => 'Azerbaijani', 'native_name' => 'Azərbaycanca', 'is_rtl' => false, 'is_default' => false, 'sort' => 4],
            ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'is_rtl' => false, 'is_default' => false, 'sort' => 5],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'is_rtl' => false, 'is_default' => false, 'sort' => 6],
            ['code' => 'pt', 'name' => 'Portuguese', 'native_name' => 'Português', 'is_rtl' => false, 'is_default' => false, 'sort' => 7],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'is_rtl' => false, 'is_default' => false, 'sort' => 8],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'is_rtl' => false, 'is_default' => false, 'sort' => 9],
            ['code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano', 'is_rtl' => false, 'is_default' => false, 'sort' => 10],
            ['code' => 'hi', 'name' => 'Hindi', 'native_name' => 'हिन्दी', 'is_rtl' => false, 'is_default' => false, 'sort' => 11],
            ['code' => 'id', 'name' => 'Indonesian', 'native_name' => 'Bahasa Indonesia', 'is_rtl' => false, 'is_default' => false, 'sort' => 12],
            ['code' => 'zh', 'name' => 'Chinese (Simplified)', 'native_name' => '简体中文', 'is_rtl' => false, 'is_default' => false, 'sort' => 13],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(['code' => $language['code']], $language + ['is_active' => true]);
        }

        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'symbol_position' => 'before', 'decimals' => 2, 'is_default' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'symbol_position' => 'before', 'decimals' => 2, 'is_default' => false],
            ['code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺', 'symbol_position' => 'after', 'decimals' => 2, 'decimal_separator' => ',', 'thousands_separator' => '.', 'is_default' => false],
            ['code' => 'AZN', 'name' => 'Azerbaijani Manat', 'symbol' => '₼', 'symbol_position' => 'after', 'decimals' => 2, 'decimal_separator' => ',', 'thousands_separator' => ' ', 'is_default' => false],
            ['code' => 'RUB', 'name' => 'Russian Ruble', 'symbol' => '₽', 'symbol_position' => 'after', 'decimals' => 2, 'decimal_separator' => ',', 'thousands_separator' => ' ', 'is_default' => false],
        ];

        // The rest of the world's common currencies: code, name, symbol, symbol position, decimals, decimal separator, thousands separator.
        foreach ([
            ['GBP', 'British Pound', '£', 'before', 2, '.', ','], ['CAD', 'Canadian Dollar', 'CA$', 'before', 2, '.', ','], ['AUD', 'Australian Dollar', 'A$', 'before', 2, '.', ','],
            ['NZD', 'New Zealand Dollar', 'NZ$', 'before', 2, '.', ','], ['CHF', 'Swiss Franc', 'CHF', 'before', 2, '.', "'"], ['SEK', 'Swedish Krona', 'kr', 'after', 2, ',', ' '],
            ['NOK', 'Norwegian Krone', 'kr', 'after', 2, ',', ' '], ['DKK', 'Danish Krone', 'kr', 'after', 2, ',', '.'], ['PLN', 'Polish Zloty', 'zł', 'after', 2, ',', ' '],
            ['CZK', 'Czech Koruna', 'Kč', 'after', 2, ',', ' '], ['HUF', 'Hungarian Forint', 'Ft', 'after', 0, ',', ' '], ['RON', 'Romanian Leu', 'lei', 'after', 2, ',', '.'],
            ['UAH', 'Ukrainian Hryvnia', '₴', 'after', 2, ',', ' '], ['KZT', 'Kazakhstani Tenge', '₸', 'after', 2, ',', ' '], ['GEL', 'Georgian Lari', '₾', 'after', 2, ',', ' '],
            ['JPY', 'Japanese Yen', '¥', 'before', 0, '.', ','], ['CNY', 'Chinese Yuan', '¥', 'before', 2, '.', ','], ['KRW', 'South Korean Won', '₩', 'before', 0, '.', ','],
            ['INR', 'Indian Rupee', '₹', 'before', 2, '.', ','], ['IDR', 'Indonesian Rupiah', 'Rp', 'before', 0, ',', '.'], ['THB', 'Thai Baht', '฿', 'before', 2, '.', ','],
            ['MYR', 'Malaysian Ringgit', 'RM', 'before', 2, '.', ','], ['SGD', 'Singapore Dollar', 'S$', 'before', 2, '.', ','], ['PHP', 'Philippine Peso', '₱', 'before', 2, '.', ','],
            ['VND', 'Vietnamese Dong', '₫', 'after', 0, ',', '.'], ['PKR', 'Pakistani Rupee', 'Rs', 'before', 0, '.', ','], ['BDT', 'Bangladeshi Taka', '৳', 'before', 2, '.', ','],
            ['AED', 'UAE Dirham', 'د.إ', 'after', 2, '.', ','], ['SAR', 'Saudi Riyal', 'ر.س', 'after', 2, '.', ','], ['QAR', 'Qatari Riyal', 'ر.ق', 'after', 2, '.', ','],
            ['KWD', 'Kuwaiti Dinar', 'د.ك', 'after', 3, '.', ','], ['EGP', 'Egyptian Pound', 'ج.م', 'after', 2, '.', ','], ['MAD', 'Moroccan Dirham', 'د.م.', 'after', 2, '.', ','],
            ['ILS', 'Israeli Shekel', '₪', 'before', 2, '.', ','], ['ZAR', 'South African Rand', 'R', 'before', 2, '.', ' '], ['NGN', 'Nigerian Naira', '₦', 'before', 2, '.', ','],
            ['KES', 'Kenyan Shilling', 'KSh', 'before', 2, '.', ','], ['GHS', 'Ghanaian Cedi', 'GH₵', 'before', 2, '.', ','], ['BRL', 'Brazilian Real', 'R$', 'before', 2, ',', '.'],
            ['MXN', 'Mexican Peso', 'MX$', 'before', 2, '.', ','], ['ARS', 'Argentine Peso', 'AR$', 'before', 2, ',', '.'], ['CLP', 'Chilean Peso', 'CLP$', 'before', 0, ',', '.'],
            ['COP', 'Colombian Peso', 'COL$', 'before', 0, ',', '.'], ['PEN', 'Peruvian Sol', 'S/', 'before', 2, '.', ','],
        ] as [$code, $name, $symbol, $position, $decimals, $decimalSeparator, $thousandsSeparator]) {
            $currencies[] = ['code' => $code, 'name' => $name, 'symbol' => $symbol, 'symbol_position' => $position, 'decimals' => $decimals, 'decimal_separator' => $decimalSeparator, 'thousands_separator' => $thousandsSeparator, 'is_default' => false];
        }

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(['code' => $currency['code']], $currency + ['is_active' => true]);
        }
    }
}
