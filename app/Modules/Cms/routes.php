<?php

use App\Modules\Cms\Http\Controllers\SiteController;
use App\Modules\Core\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SetLocale::class])->group(function () {
    Route::get('/', [SiteController::class, 'landing'])->name('home');
    Route::get('/blog', [SiteController::class, 'blog'])->name('blog.index');
    Route::get('/blog/{slug}', [SiteController::class, 'post'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('blog.show');
    Route::get('/p/{slug}', [SiteController::class, 'page'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('pages.show');
});

Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->middleware('web')->name('sitemap');
