<?php

namespace App\Modules\Menu\Database\Seeders;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\OptionGroup;
use App\Modules\Menu\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Menu of one demo restaurant, picked by its slug. Dishes carry no photos: the customer menu shows
 * the built-in illustrations for them, which is also how a freshly installed restaurant looks.
 * Item format: [name, price, description, extras] where names and texts are [en, tr].
 */
class MenuDemoSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = app(TenantContext::class)->get();
        $menu = $this->menus()[$restaurant->slug] ?? null;

        if (! $menu) {
            return;
        }

        Category::query()->delete();
        Product::query()->delete();
        OptionGroup::query()->delete();

        $groups = [];

        foreach ($menu['groups'] as $key => [$name, $type, $required, $options]) {
            $group = OptionGroup::create(['name' => $this->t($name), 'type' => $type, 'is_required' => $required, 'sort' => count($groups) + 1]);

            foreach ($options as $i => [$option, $delta, $default]) {
                $group->options()->create(['name' => $this->t($option), 'price_delta' => $delta, 'is_default' => $default, 'sort' => $i + 1]);
            }

            $groups[$key] = $group;
        }

        foreach ($menu['categories'] as $c => [$name, $items]) {
            $category = Category::create(['name' => $this->t($name), 'sort' => $c + 1]);

            foreach ($items as $i => [$title, $price, $text, $extra]) {
                $product = Product::create([
                    'category_id' => $category->id, 'name' => $this->t($title), 'description' => $this->t($text), 'price' => $price, 'sort' => $i + 1,
                    'is_featured' => $extra['featured'] ?? false, 'is_available' => $extra['available'] ?? true,
                    'compare_price' => $extra['was'] ?? null, 'calories' => $extra['kcal'] ?? null, 'prep_minutes' => $extra['min'] ?? null,
                    'allergens' => $extra['allergens'] ?? null, 'dietary' => $extra['diet'] ?? null,
                ]);

                foreach ((array) ($extra['groups'] ?? []) as $n => $key) {
                    $product->optionGroups()->attach($groups[$key]->id, ['sort' => $n]);
                }
            }
        }
    }

    /** @param array{string, string} $pair [en, tr] */
    private function t(array|string $pair): array
    {
        return is_array($pair) ? ['en' => $pair[0], 'tr' => $pair[1]] : ['en' => $pair, 'tr' => $pair];
    }

    /** @return array<string, array<string, mixed>> */
    private function menus(): array
    {
        $size = ['Size', 'single', true, [[['Regular', 'Normal'], 0, true], [['Large', 'Büyük'], 3, false]]];

        return [
            'bella-italia' => [
                'groups' => [
                    'size' => [['Size', 'Boy'], 'single', true, [[['Regular (30 cm)', 'Normal (30 cm)'], 0, true], [['Large (40 cm)', 'Büyük (40 cm)'], 4, false]]],
                    'extras' => [['Extras', 'Ekstralar'], 'multiple', false, [[['Extra mozzarella', 'Ekstra mozzarella'], 1.5, false], [['Fresh basil', 'Taze fesleğen'], 0.5, false], [['Spicy oil', 'Acı yağ'], 0.5, false], [['Prosciutto', 'Prosciutto'], 2.5, false]]],
                ],
                'categories' => [
                    [['Starters', 'Başlangıçlar'], [
                        [['Bruschetta', 'Bruschetta'], 7.5, ['Grilled bread, ripe tomatoes, basil and olive oil.', 'Izgara ekmek, olgun domates, fesleğen ve zeytinyağı.'], ['featured' => true, 'kcal' => 240, 'min' => 6, 'allergens' => ['gluten'], 'diet' => ['vegan']]],
                        [['Burrata', 'Burrata'], 11, ['Creamy burrata, cherry tomatoes, pesto and toasted pine nuts.', 'Kremsi burrata, kiraz domates, pesto ve kavrulmuş çam fıstığı.'], ['was' => 13, 'allergens' => ['milk', 'nuts'], 'diet' => ['vegetarian']]],
                        [['Garlic Bread', 'Sarımsaklı Ekmek'], 5, ['Warm ciabatta with garlic butter and parsley.', 'Sarımsaklı tereyağlı sıcak ciabatta.'], ['allergens' => ['gluten', 'milk'], 'diet' => ['vegetarian']]],
                    ]],
                    [['Pizza', 'Pizza'], [
                        [['Margherita', 'Margherita'], 12, ['San Marzano tomato, fior di latte and fresh basil on a 48h dough.', 'San Marzano domates, taze mozzarella ve fesleğen, 48 saat mayalı hamur.'], ['featured' => true, 'kcal' => 780, 'min' => 12, 'allergens' => ['gluten', 'milk'], 'diet' => ['vegetarian'], 'groups' => ['size', 'extras']]],
                        [['Diavola', 'Diavola'], 14.5, ['Spicy salame, tomato, mozzarella and chilli oil.', 'Acılı salam, domates, mozzarella ve acı yağ.'], ['kcal' => 860, 'min' => 12, 'allergens' => ['gluten', 'milk'], 'diet' => ['spicy'], 'groups' => ['size', 'extras']]],
                        [['Quattro Formaggi', 'Dört Peynirli'], 15, ['Mozzarella, gorgonzola, parmesan and fontina.', 'Mozzarella, gorgonzola, parmesan ve fontina.'], ['available' => false, 'allergens' => ['gluten', 'milk'], 'diet' => ['vegetarian'], 'groups' => ['size']]],
                    ]],
                    [['Pasta', 'Makarna'], [
                        [['Spaghetti Carbonara', 'Spaghetti Carbonara'], 13.5, ['Guanciale, egg yolk, pecorino and black pepper.', 'Guanciale, yumurta sarısı, pecorino ve karabiber.'], ['featured' => true, 'allergens' => ['gluten', 'eggs', 'milk']]],
                        [['Penne Arrabbiata', 'Penne Arrabbiata'], 11, ['Penne in a spicy tomato and garlic sauce.', 'Acılı domates ve sarımsak soslu penne.'], ['allergens' => ['gluten'], 'diet' => ['vegan', 'spicy']]],
                    ]],
                    [['Desserts & Drinks', 'Tatlı & İçecek'], [
                        [['Tiramisu', 'Tiramisu'], 6.5, ['Mascarpone cream, espresso-soaked savoiardi and cocoa.', 'Mascarpone kreması, espressolu savoiardi ve kakao.'], ['featured' => true, 'allergens' => ['gluten', 'eggs', 'milk']]],
                        [['Lemonade', 'Limonata'], 4, ['Fresh-squeezed lemons, mint and a little sparkle.', 'Taze sıkılmış limon, nane ve hafif gazlı.'], ['diet' => ['vegan']]],
                        [['Espresso', 'Espresso'], 2.5, ['Single origin, short and strong.', 'Tek menşe, kısa ve yoğun.'], []],
                    ]],
                ],
            ],
            'sushi-zen' => [
                'groups' => [
                    'pieces' => [['Portion', 'Porsiyon'], 'single', true, [[['6 pieces', '6 parça'], 0, true], [['12 pieces', '12 parça'], 7, false]]],
                    'sauce' => [['Sauces', 'Soslar'], 'multiple', false, [[['Soy sauce', 'Soya sosu'], 0, true], [['Spicy mayo', 'Acılı mayo'], 0.5, false], [['Teriyaki', 'Teriyaki'], 0.5, false], [['Wasabi', 'Wasabi'], 0, false]]],
                ],
                'categories' => [
                    [['Starters', 'Başlangıçlar'], [
                        [['Edamame', 'Edamame'], 5, ['Steamed soybeans with sea salt.', 'Deniz tuzlu buharda soya fasulyesi.'], ['diet' => ['vegan'], 'allergens' => ['soy']]],
                        [['Gyoza', 'Gyoza'], 7.5, ['Pan-fried pork dumplings with ponzu.', 'Ponzu soslu tavada pişmiş dana hamur mantı.'], ['allergens' => ['gluten', 'soy', 'sesame']]],
                        [['Miso Soup', 'Miso Çorbası'], 4, ['Dashi, white miso, tofu and wakame.', 'Dashi, beyaz miso, tofu ve wakame.'], ['allergens' => ['soy', 'fish']]],
                    ]],
                    [['Rolls', 'Rolller'], [
                        [['Salmon Avocado Roll', 'Somon Avokado Roll'], 9.5, ['Fresh salmon, avocado and cucumber.', 'Taze somon, avokado ve salatalık.'], ['featured' => true, 'allergens' => ['fish', 'sesame'], 'groups' => ['pieces', 'sauce']]],
                        [['Spicy Tuna Roll', 'Acılı Ton Roll'], 10, ['Tuna, spicy mayo and crispy tempura flakes.', 'Ton balığı, acılı mayo ve çıtır tempura.'], ['allergens' => ['fish', 'gluten', 'eggs'], 'diet' => ['spicy'], 'groups' => ['pieces', 'sauce']]],
                        [['Veggie Roll', 'Sebzeli Roll'], 8, ['Cucumber, carrot, avocado and pickled radish.', 'Salatalık, havuç, avokado ve turşu turp.'], ['diet' => ['vegan'], 'allergens' => ['sesame'], 'groups' => ['pieces', 'sauce']]],
                    ]],
                    [['Mains', 'Ana Yemekler'], [
                        [['Chicken Teriyaki Bowl', 'Teriyaki Tavuk Kasesi'], 13, ['Grilled chicken, rice, edamame and teriyaki glaze.', 'Izgara tavuk, pilav, edamame ve teriyaki sos.'], ['featured' => true, 'allergens' => ['soy', 'sesame']]],
                        [['Beef Ramen', 'Dana Ramen'], 14.5, ['Slow-cooked broth, noodles, egg and nori.', 'Uzun pişmiş et suyu, erişte, yumurta ve nori.'], ['allergens' => ['gluten', 'eggs', 'soy']]],
                    ]],
                    [['Drinks', 'İçecekler'], [
                        [['Iced Green Tea', 'Buzlu Yeşil Çay'], 3.5, ['Sencha, lightly sweetened, over ice.', 'Sencha, hafif tatlı, buzlu.'], ['diet' => ['vegan']]],
                        [['Yuzu Lemonade', 'Yuzu Limonata'], 4.5, ['Sparkling yuzu and lemon.', 'Gazlı yuzu ve limon.'], ['diet' => ['vegan']]],
                    ]],
                ],
            ],
            'kahve-duragi' => [
                'groups' => [
                    'size' => [['Size', 'Boy'], 'single', true, [[['Small', 'Küçük'], 0, true], [['Large', 'Büyük'], 12, false]]],
                    'milk' => [['Milk', 'Süt'], 'single', false, [[['Whole milk', 'Tam yağlı süt'], 0, true], [['Oat milk', 'Yulaf sütü'], 8, false], [['Almond milk', 'Badem sütü'], 8, false]]],
                ],
                'categories' => [
                    [['Coffee', 'Kahve'], [
                        [['Flat White', 'Flat White'], 95, ['Double ristretto with silky steamed milk.', 'Çift ristretto ve kadifemsi buharlanmış süt.'], ['featured' => true, 'allergens' => ['milk'], 'groups' => ['size', 'milk']]],
                        [['Latte', 'Latte'], 90, ['Espresso with plenty of steamed milk.', 'Espresso ve bol buharlanmış süt.'], ['allergens' => ['milk'], 'groups' => ['size', 'milk']]],
                        [['Türk Kahvesi', 'Türk Kahvesi'], 70, ['Traditional, cooked slowly on hot sand.', 'Geleneksel, sıcak kumda yavaş pişirilir.'], ['featured' => true]],
                    ]],
                    [['Tea & Cold', 'Çay & Soğuk'], [
                        [['Çay', 'Çay'], 35, ['Freshly brewed black tea in a tulip glass.', 'İnce belli bardakta taze demlenmiş çay.'], ['diet' => ['vegan']]],
                        [['Iced Latte', 'Buzlu Latte'], 100, ['Espresso, cold milk and ice.', 'Espresso, soğuk süt ve buz.'], ['allergens' => ['milk'], 'groups' => ['size', 'milk']]],
                        [['Limonata', 'Limonata'], 80, ['Hand-squeezed lemons with mint.', 'El sıkması limon ve nane.'], ['diet' => ['vegan']]],
                    ]],
                    [['Sweet', 'Tatlılar'], [
                        [['San Sebastian Cheesecake', 'San Sebastian Cheesecake'], 150, ['Creamy burnt cheesecake with berry sauce.', 'Kremsi yanık cheesecake ve orman meyvesi sosu.'], ['featured' => true, 'was' => 175, 'allergens' => ['milk', 'eggs', 'gluten']]],
                        [['Baklava', 'Baklava'], 140, ['Pistachio baklava, four layers, with kaymak.', 'Antep fıstıklı baklava, kaymak ile.'], ['allergens' => ['gluten', 'nuts', 'milk']]],
                    ]],
                    [['Light Bites', 'Atıştırmalık'], [
                        [['Cheese Toast', 'Kaşarlı Tost'], 110, ['Melted kaşar on thick sourdough.', 'Kalın ekşi mayalı ekmekte eritilmiş kaşar.'], ['allergens' => ['gluten', 'milk'], 'diet' => ['vegetarian']]],
                        [['Simit', 'Simit'], 40, ['Sesame-crusted, baked fresh each morning.', 'Her sabah taze fırınlanmış susamlı simit.'], ['allergens' => ['gluten', 'sesame'], 'diet' => ['vegan']]],
                    ]],
                ],
            ],
        ];
    }
}
