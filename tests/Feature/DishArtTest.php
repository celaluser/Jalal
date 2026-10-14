<?php

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Services\MenuService;
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Menu\Support\DishArt;
use App\Modules\Tables\Qr\QrStyle;
use App\Modules\Tenancy\Models\Restaurant;

it('picks an illustration from the dish name, in several languages', function (string $name, string $motif) {
    expect(DishArt::motif($name))->toBe($motif);
})->with([
    ['Margherita Pizza', 'pizza'], ['Cheeseburger', 'burger'], ['Spaghetti Carbonara', 'pasta'], ['Greek Salad', 'salad'],
    ['Mercimek Çorbası', 'soup'], ['Tiramisu', 'dessert'], ['Sütlaç Tatlı', 'dessert'], ['Latte', 'coffee'], ['Türk Çayı', 'coffee'],
    ['Fresh Lemonade', 'drink'], ['Ayran', 'drink'], ['Ribeye Steak', 'grill'], ['Adana Kebap', 'grill'], ['Bruschetta', 'starter'],
    ['Salmon Avocado Roll', 'sushi'], ['Gyoza', 'dumpling'], ['Chicken Teriyaki Bowl', 'bowl'], ['Edamame', 'salad'], ['Iced Green Tea', 'drink'], ['Iced Latte', 'drink'], ['بيتزا مارجريتا', 'pizza'], ['قهوة', 'coffee'], ['Mystery Dish', 'plate'], ['', 'plate'],
]);

it('only treats short keywords as whole words', function () {
    expect(DishArt::motif('Superfood special'))->toBe('plate')   // "su" must not match the start of another word
        ->and(DishArt::motif('Su'))->toBe('drink')
        ->and(DishArt::motif('Wrap of the day'))->toBe('burger');
});

it('falls back to the category name, preferring the product name', function () {
    expect(DishArt::motif('House special', 'Starters'))->toBe('plate') // a section name alone says nothing about the dish
        ->and(DishArt::motif('House special', 'Desserts'))->toBe('dessert')
        ->and(DishArt::motif('Chocolate Cake', 'Pizza'))->toBe('dessert')
        ->and(DishArt::motif('Mystery', 'Mystery'))->toBe('plate');
});

it('ships an illustration file for every motif', function () {
    foreach (DishArt::motifs() as $motif) {
        $file = public_path("img/dish/{$motif}.svg");

        expect(is_file($file))->toBeTrue("missing {$motif}.svg")->and(simplexml_load_file($file))->not->toBeFalse();
    }
});

it('gives photo-less dishes an illustration in the customer menu data and the product model', function () {
    $r = Restaurant::create(['name' => 'Art Cafe', 'slug' => 'art'.uniqid(), 'locale' => 'en']);

    app(TenantContext::class)->runAs($r, function () use ($r) {
        $cat = Category::create(['name' => ['en' => 'Pizza'], 'sort' => 1]);
        $p = Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Margherita'], 'price' => 9]);

        $tree = app(MenuService::class)->tree($r);

        expect($tree[0]['products'][0]['image'])->toBeNull()->and($tree[0]['products'][0]['art'])->toEndWith('/img/dish/pizza.svg')
            ->and($p->load('category')->pictureUrl())->toEndWith('/img/dish/pizza.svg');
    });
});

it('keeps text readable on both ends of the hero gradient for any brand colour', function (string $brand) {
    $r = Restaurant::create(['name' => 'X', 'slug' => 'x'.uniqid(), 'branding' => ['color' => $brand]]);
    $t = app(ThemeRegistry::class)->tokens($r);

    expect(QrStyle::contrast($t['--menu-accent-fg'], $t['--menu-accent']))->toBeGreaterThanOrEqual(4.0) // bold button text on a mid-tone brand colour: the better of ink and white
        ->and(QrStyle::contrast($t['--menu-accent-fg'], $t['--menu-accent-2']))->toBeGreaterThanOrEqual(3.0);
})->with(['#ffb020', '#ffffff', '#000000', '#1c8e73', '#c0392b', '#2a78d6', '#7b2d8e', '#f5e663']);
