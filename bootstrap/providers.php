<?php

use App\Modules\Activity\Providers\ActivityServiceProvider;
use App\Modules\Addons\Providers\AddonsServiceProvider;
use App\Modules\Admin\Providers\AdminServiceProvider;
use App\Modules\Affiliate\Providers\AffiliateServiceProvider;
use App\Modules\Ai\Providers\AiServiceProvider;
use App\Modules\Analytics\Providers\AnalyticsServiceProvider;
use App\Modules\Api\Providers\ApiServiceProvider;
use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Branches\Providers\BranchesServiceProvider;
use App\Modules\Cms\Providers\CmsServiceProvider;
use App\Modules\Core\Providers\CoreServiceProvider;
use App\Modules\Demo\Providers\DemoServiceProvider;
use App\Modules\Installer\Providers\InstallerServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Licensing\Providers\LicensingServiceProvider;
use App\Modules\Marketing\Providers\MarketingServiceProvider;
use App\Modules\Menu\Providers\MenuServiceProvider;
use App\Modules\Messaging\Providers\MessagingServiceProvider;
use App\Modules\Orders\Providers\OrdersServiceProvider;
use App\Modules\Reservations\Providers\ReservationsServiceProvider;
use App\Modules\Store\Providers\StoreServiceProvider;
use App\Modules\Storefront\Providers\StorefrontServiceProvider;
use App\Modules\Support\Providers\SupportServiceProvider;
use App\Modules\Tables\Providers\TablesServiceProvider;
use App\Modules\Team\Providers\TeamServiceProvider;
use App\Modules\Tenancy\Providers\TenancyServiceProvider;
use App\Modules\Updater\Providers\UpdaterServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    TenancyServiceProvider::class,
    BillingServiceProvider::class,
    AffiliateServiceProvider::class,
    AdminServiceProvider::class,
    CmsServiceProvider::class,
    SupportServiceProvider::class,
    MenuServiceProvider::class,
    BranchesServiceProvider::class,
    InventoryServiceProvider::class,
    StoreServiceProvider::class,
    ApiServiceProvider::class,
    TablesServiceProvider::class,
    OrdersServiceProvider::class,
    MarketingServiceProvider::class,
    ReservationsServiceProvider::class,
    ActivityServiceProvider::class,
    MessagingServiceProvider::class,
    AnalyticsServiceProvider::class,
    AiServiceProvider::class,
    TeamServiceProvider::class,
    StorefrontServiceProvider::class,
    AuthServiceProvider::class,
    LicensingServiceProvider::class,
    InstallerServiceProvider::class,
    UpdaterServiceProvider::class,
    DemoServiceProvider::class,
    AddonsServiceProvider::class, // keep last: add-ons use every registry above
];
