<?php

use App\Modules\Admin\Providers\AdminServiceProvider;
use App\Modules\Ai\Providers\AiServiceProvider;
use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Cms\Providers\CmsServiceProvider;
use App\Modules\Core\Providers\CoreServiceProvider;
use App\Modules\Demo\Providers\DemoServiceProvider;
use App\Modules\Installer\Providers\InstallerServiceProvider;
use App\Modules\Licensing\Providers\LicensingServiceProvider;
use App\Modules\Menu\Providers\MenuServiceProvider;
use App\Modules\Orders\Providers\OrdersServiceProvider;
use App\Modules\Storefront\Providers\StorefrontServiceProvider;
use App\Modules\Support\Providers\SupportServiceProvider;
use App\Modules\Tables\Providers\TablesServiceProvider;
use App\Modules\Tenancy\Providers\TenancyServiceProvider;
use App\Modules\Updater\Providers\UpdaterServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    TenancyServiceProvider::class,
    BillingServiceProvider::class,
    AdminServiceProvider::class,
    CmsServiceProvider::class,
    SupportServiceProvider::class,
    MenuServiceProvider::class,
    TablesServiceProvider::class,
    OrdersServiceProvider::class,
    AiServiceProvider::class,
    StorefrontServiceProvider::class,
    AuthServiceProvider::class,
    LicensingServiceProvider::class,
    InstallerServiceProvider::class,
    UpdaterServiceProvider::class,
    DemoServiceProvider::class,
];
