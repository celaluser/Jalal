<?php

namespace App\Modules\Menu\Support;

/**
 * Illustration used when a dish has no photo of its own, so a menu never shows empty grey boxes.
 * The motif is guessed from the product and category names (English, Turkish and Arabic keywords);
 * anything unrecognised gets the generic serving dome. The files live in public/img/dish.
 */
final class DishArt
{
    public const GENERIC = 'plate';

    /** Checked in this order: the first motif with a matching keyword wins. */
    private const KEYWORDS = [
        'pizza' => ['pizza', 'margherita', 'pide', 'lahmacun', 'calzone', 'بيتزا'],
        'burger' => ['burger', 'hamburger', 'sandwich', 'sandviç', 'sandvic', 'wrap', 'hot dog', 'hotdog', 'döner', 'doner', 'برغر', 'ساندويتش'],
        'pasta' => ['pasta', 'spaghetti', 'penne', 'lasagna', 'lasagne', 'noodle', 'makarna', 'risotto', 'ravioli', 'gnocchi', 'mantı', 'manti', 'معكرونة', 'باستا'],
        'salad' => ['salad', 'salata', 'bowl', 'çoban', 'coban', 'سلطة'],
        'soup' => ['soup', 'çorba', 'corba', 'broth', 'ramen', 'شوربة', 'حساء'],
        'dessert' => ['dessert', 'cake', 'tiramisu', 'cheesecake', 'ice cream', 'icecream', 'gelato', 'dondurma', 'tatlı', 'tatli', 'baklava', 'pudding', 'brownie', 'waffle', 'sweet', 'pastry', 'حلويات', 'كيك', 'آيس'],
        'coffee' => ['coffee', 'espresso', 'latte', 'cappuccino', 'americano', 'mocha', 'tea', 'çay', 'cay', 'kahve', 'türk kahvesi', 'قهوة', 'شاي'],
        'drink' => ['drink', 'juice', 'lemonade', 'limonata', 'soda', 'cola', 'cocktail', 'mocktail', 'smoothie', 'beverage', 'içecek', 'icecek', 'meyve suyu', 'ayran', 'water', 'su', 'beer', 'wine', 'mojito', 'عصير', 'مشروب', 'ليمون'],
        'grill' => ['steak', 'grill', 'bbq', 'kebab', 'kebap', 'kebob', 'ribs', 'chicken', 'tavuk', 'köfte', 'kofte', 'meat', 'lamb', 'kuzu', 'beef', 'fish', 'balık', 'balik', 'salmon', 'shrimp', 'et', 'izgara', 'مشاوي', 'دجاج', 'لحم', 'ستيك'],
        'starter' => ['starter', 'appetizer', 'appetiser', 'bruschetta', 'meze', 'bread', 'ekmek', 'fries', 'patates', 'snack', 'antipasti', 'burrata', 'hummus', 'falafel', 'nachos', 'wings', 'başlangıç', 'baslangic', 'مقبلات', 'خبز'],
    ];

    /** Short words that must match whole ("tea" is not "steak", "su" is not "sushi"); all others match at the start of a word. */
    private const WHOLE_WORD = ['su', 'tea', 'et', 'wrap', 'cola', 'beer', 'cay', 'çay'];

    /** @param string ...$texts names to look at, most specific first (product name, then category name) */
    public static function motif(string ...$texts): string
    {
        foreach ($texts as $text) {
            $haystack = mb_strtolower(trim($text));

            if ($haystack === '') {
                continue;
            }

            foreach (self::KEYWORDS as $motif => $words) {
                foreach ($words as $word) {
                    if (self::matches($haystack, trim($word))) {
                        return $motif;
                    }
                }
            }
        }

        return self::GENERIC;
    }

    public static function url(string ...$texts): string
    {
        return asset('img/dish/'.self::motif(...$texts).'.svg');
    }

    /** @return list<string> */
    public static function motifs(): array
    {
        return [...array_keys(self::KEYWORDS), self::GENERIC];
    }

    /** Long keywords match anywhere ("cheeseburger"); short ones at the start of a word ("çayı", "lattes" count, "platter" does not). */
    private static function matches(string $haystack, string $word): bool
    {
        $tail = in_array($word, self::WHOLE_WORD, true) && $word !== 'çay' && $word !== 'cay' ? '(?![\p{L}\p{N}])' : '';

        $anywhere = mb_strlen($word) >= 6 ? '' : '(?<![\p{L}\p{N}])(?:ال)?';

        return (bool) preg_match('/'.$anywhere.preg_quote($word, '/').$tail.'/u', $haystack);
    }
}
