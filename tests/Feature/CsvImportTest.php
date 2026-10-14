<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Menu\Services\CsvMenuImport;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
});

function ciShop(array $limits = []): array
{
    $r = Restaurant::create(['name' => 'Csv', 'slug' => 'csv'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now()]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 1, 'currency_code' => 'USD', 'limits' => $limits, 'features' => []]), now()->addMonth());
    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $owner->assignRole(Permissions::OWNER);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return [$r, $owner];
}

function ciFile(string $content, string $name = 'menu.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

function ciParse(Restaurant $r, string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $content);

    return app(TenantContext::class)->runAs($r, fn () => app(CsvMenuImport::class)->parse($path, $r));
}

it('reads rows in any column order, with ; or , and a spreadsheet BOM, and cleans the values', function () {
    [$r] = ciShop();
    $out = ciParse($r, "\xEF\xBB\xBFFiyat;Kategori;Yemek;Stok;Açıklama\n7,50;Starters;Bruschetta;12;<b>Tomato</b> bread\n3;Drinks;Lemonade;;");
    expect($out['errors'])->toBe([])->and($out['rows'])->toHaveCount(2)->and($out['rows'][0])->toMatchArray(['category' => 'Starters', 'dish' => 'Bruschetta', 'price' => 7.5, 'stock' => 12, 'description' => 'Tomato bread', 'exists' => false])
        ->and($out['rows'][1]['stock'])->toBeNull()->and($out['new_categories'])->toBe(2)->and($out['new_products'])->toBe(2);
});

it('reads a Windows-1254 file saved by a Turkish spreadsheet', function () {
    [$r] = ciShop();
    $csv = mb_convert_encoding("Category;Dish;Price\nTatlılar;Künefe;9\nİçecekler;Şıra;2", 'Windows-1254', 'UTF-8');
    $out = ciParse($r, $csv);
    expect(collect($out['rows'])->pluck('dish')->all())->toBe(['Künefe', 'Şıra'])->and($out['rows'][1]['category'])->toBe('İçecekler');
});

it('reports bad rows by line and skips them', function () {
    [$r] = ciShop();
    $out = ciParse($r, "category,dish,price,stock\nA,Ok,5,\n,NoCategory,5,\nA,BadPrice,abc,\nA,BadStock,5,x\nA,Ok,6,\nA,Negative,-1,");
    expect($out['rows'])->toHaveCount(1)->and(collect($out['errors'])->pluck('message', 'line')->all())->toBe([3 => 'missing_name', 4 => 'bad_price', 5 => 'bad_stock', 6 => 'duplicate', 7 => 'bad_price']);
});

it('refuses a file without the needed columns, an empty one, and a huge one', function () {
    [$r] = ciShop();
    foreach (['dish,price\nA,1' => 'header', '' => 'empty'] as $csv => $code) {
        try {
            ciParse($r, str_replace('\n', "\n", $csv));
            $failed = null;
        } catch (InvalidArgumentException $e) {
            $failed = $e->getMessage();
        }
        expect($failed)->toBe($code);
    }
    $big = "category,dish,price\n".str_repeat("A,Dish,1\n", 1001);
    expect(fn () => ciParse($r, $big))->toThrow(InvalidArgumentException::class, 'too_many');
});

it('previews first, saves nothing until applied, then creates and updates dishes', function () {
    [$r, $owner] = ciShop();
    $cat = app(TenantContext::class)->runAs($r, fn () => Category::create(['name' => ['en' => 'Starters'], 'sort' => 1]));
    $old = app(TenantContext::class)->runAs($r, fn () => Product::create(['category_id' => $cat->id, 'name' => ['en' => 'Bruschetta'], 'price' => 5, 'sort' => 1]));

    $this->actingAs($owner)->post(route('menu.import.preview'), ['file' => ciFile("Category,Dish,Description,Price,Stock,Available,Calories\nStarters,Bruschetta,Grilled bread,7.50,20,no,210\nDrinks,Lemonade,,3,,yes,\n")])->assertRedirect(route('menu.import'));
    expect(app(TenantContext::class)->runAs($r, fn () => Product::count()))->toBe(1);

    $this->actingAs($owner)->get(route('menu.import'))->assertOk()->assertSee('Lemonade')->assertSee('Bruschetta');
    $this->actingAs($owner)->post(route('menu.import.apply'))->assertRedirect(route('menu.index'))->assertSessionHas('status');

    $updated = app(TenantContext::class)->runAs($r, fn () => $old->fresh());
    expect((float) $updated->price)->toBe(7.5)->and($updated->stock_qty)->toBe(20)->and($updated->is_available)->toBeFalse()->and($updated->calories)->toBe(210)->and($updated->description['en'])->toBe('Grilled bread');
    $lemonade = app(TenantContext::class)->runAs($r, fn () => Product::where('name->en', 'Lemonade')->first());
    expect($lemonade)->not->toBeNull()->and($lemonade->category->name['en'])->toBe('Drinks')->and(app(TenantContext::class)->runAs($r, fn () => Product::count()))->toBe(2);

    // Applying again without a new preview does nothing.
    $this->actingAs($owner)->post(route('menu.import.apply'))->assertRedirect(route('menu.import'));
});

it('respects the plan limits for the whole batch and changes nothing when it does not fit', function () {
    [$r, $owner] = ciShop(['products' => 1]);
    $this->actingAs($owner)->post(route('menu.import.preview'), ['file' => ciFile("category,dish,price\nA,One,1\nA,Two,2\n")]);
    $this->actingAs($owner)->post(route('menu.import.apply'))->assertSessionHasErrors('file');
    expect(app(TenantContext::class)->runAs($r, fn () => Product::count()))->toBe(0)->and(app(TenantContext::class)->runAs($r, fn () => Category::count()))->toBe(0);
});

it('never touches another restaurant’s dishes with the same names', function () {
    [$r, $owner] = ciShop();
    [$other] = ciShop();
    $theirs = app(TenantContext::class)->runAs($other, function () {
        $c = Category::create(['name' => ['en' => 'Starters'], 'sort' => 1]);

        return Product::create(['category_id' => $c->id, 'name' => ['en' => 'Bruschetta'], 'price' => 5, 'sort' => 1]);
    });
    $this->actingAs($owner)->post(route('menu.import.preview'), ['file' => ciFile("category,dish,price\nStarters,Bruschetta,99\n")]);
    $this->actingAs($owner)->post(route('menu.import.apply'));
    expect((float) app(TenantContext::class)->runAs($other, fn () => $theirs->fresh()->price))->toBe(5.0);
});

it('rejects other file types, offers a sample, and is for people who manage the menu', function () {
    [$r, $owner] = ciShop();
    $this->actingAs($owner)->post(route('menu.import.preview'), ['file' => UploadedFile::fake()->create('menu.exe', 5, 'application/octet-stream')])->assertSessionHasErrors('file');
    $this->actingAs($owner)->get(route('menu.import.sample'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $waiter = User::factory()->create(['restaurant_id' => $r->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($r->id);
    $waiter->assignRole('waiter');
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->actingAs($waiter)->get(route('menu.import'))->assertForbidden();
});

function ciXlsx(array $rows): string
{
    $strings = [];
    $sheet = '';
    foreach ($rows as $r => $row) {
        $sheet .= '<row r="'.($r + 1).'">';
        foreach ($row as $c => $v) {
            $ref = chr(65 + $c).($r + 1);
            if (is_numeric($v)) {
                $sheet .= '<c r="'.$ref.'"><v>'.$v.'</v></c>';
            } else {
                $strings[] = $v;
                $sheet .= '<c r="'.$ref.'" t="s"><v>'.(count($strings) - 1).'</v></c>';
            }
        }
        $sheet .= '</row>';
    }
    $path = tempnam(sys_get_temp_dir(), 'xl').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet><sheetData>'.$sheet.'</sheetData></worksheet>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst>'.implode('', array_map(fn ($s) => '<si><t>'.htmlspecialchars($s).'</t></si>', $strings)).'</sst>');
    $zip->close();

    return $path;
}

it('imports an Excel file through the same preview as a CSV', function () {
    [$r, $owner] = ciShop();
    $path = ciXlsx([['Category', 'Dish', 'Price'], ['Soups', 'Lentil & Co', 4.5], ['Soups', 'Tomato', 5]]);
    $file = new UploadedFile($path, 'menu.xlsx', null, null, true);

    $this->actingAs($owner)->post(route('menu.import.preview'), ['file' => $file])->assertRedirect(route('menu.import'));
    $this->actingAs($owner)->post(route('menu.import.apply'))->assertRedirect();

    $names = app(TenantContext::class)->runAs($r, fn () => Product::all()->map(fn ($p) => $p->tr('name'))->sort()->values()->all());
    expect($names)->toBe(['Lentil & Co', 'Tomato']);
});

it('rejects a broken Excel file without crashing', function () {
    [$r, $owner] = ciShop();
    $bad = tempnam(sys_get_temp_dir(), 'xl');
    file_put_contents($bad, 'not a zip');

    $this->actingAs($owner)->post(route('menu.import.preview'), ['file' => new UploadedFile($bad, 'menu.xlsx', null, null, true)])->assertSessionHasErrors('file');
});
