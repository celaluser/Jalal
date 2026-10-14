<?php

namespace App\Modules\Demo\Support;

use Illuminate\Database\Seeder;

/**
 * Modules register a seeder that fills one demo restaurant with their own data
 * (menus, products, tables, orders...). DemoSeeder runs them with that restaurant as tenant.
 *
 * Usage, from a module service provider:  DemoDataRegistry::register(CatalogDemoSeeder::class);
 */
final class DemoDataRegistry
{
    /** @var list<class-string<Seeder>> */
    private static array $seeders = [];

    /**
     * @param  class-string<Seeder>  $seeder
     */
    public static function register(string $seeder): void
    {
        if (! in_array($seeder, self::$seeders, true)) {
            self::$seeders[] = $seeder;
        }
    }

    /**
     * @return list<class-string<Seeder>>
     */
    public static function all(): array
    {
        return self::$seeders;
    }

    public static function flush(): void
    {
        self::$seeders = [];
    }
}
