<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Storefront\Services\PwaIcon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Makes the guest menu installable: web app manifest, home-screen icons and a small service worker.
 * The worker keeps the menu page readable offline and never caches orders, quotes or order status.
 */
class PwaController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function manifest(Request $request): JsonResponse
    {
        $restaurant = $this->tenant->get();
        $base = $this->base($request);
        $brand = $restaurant->brandColor();

        return response()->json([
            'name' => $restaurant->name, 'short_name' => mb_substr($restaurant->name, 0, 12),
            'start_url' => $base.'/?source=pwa', 'scope' => $base.'/', 'display' => 'standalone',
            'background_color' => '#ffffff', 'theme_color' => $brand,
            'icons' => collect(PwaIcon::SIZES)->map(fn ($s) => ['src' => $base.'/pwa-icon-'.$s.'.png', 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => 'any maskable'])->all(),
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function icon(Request $request, PwaIcon $icons): Response
    {
        $size = (int) $request->route('size');
        abort_unless(in_array($size, PwaIcon::SIZES, true), 404);

        return response($icons->png($this->tenant->get(), $size), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function worker(Request $request): Response
    {
        $base = $this->base($request);
        $cache = 'menu-'.$this->tenant->get()->id.'-v1';
        $offline = e(__('customer.offline_title'));
        $offlineText = e(__('customer.offline_text'));
        $js = <<<JS
const BASE = '{$base}';
const CACHE = '{$cache}';
const OFFLINE = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$offline}</title><body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center;padding:24px"><main><h1>{$offline}</h1><p>{$offlineText}</p></main>';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k.startsWith('menu-') && k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim())));

self.addEventListener('push', (event) => {
  let d = {};
  try { d = event.data ? event.data.json() : {}; } catch (e) { /* plain text */ }
  event.waitUntil(self.registration.showNotification(d.title || 'Order update', { body: d.body || '', data: { url: d.url || BASE + '/' }, tag: 'order' }));
});
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || BASE + '/';
  event.waitUntil(self.clients.matchAll({ type: 'window' }).then((all) => { for (const c of all) { if (c.url === url && 'focus' in c) { return c.focus(); } } return self.clients.openWindow(url); }));
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin) { return; }
  // Orders, status polling and the cart price check always go to the network.
  if (/\/(order|cart|account)\b/.test(url.pathname)) { return; }

  if (req.mode === 'navigate') {
    if (url.pathname.replace(/\/\$/, '') !== BASE) { return; }
    event.respondWith(fetch(req).then((res) => { const copy = res.clone(); if (res.ok) { caches.open(CACHE).then((c) => c.put(BASE + '/', copy)); } return res; })
      .catch(() => caches.match(BASE + '/').then((hit) => hit || new Response(OFFLINE, { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }))));
    return;
  }

  // Fingerprinted build files and dish pictures: serve from cache, refresh in the background.
  if (url.pathname.startsWith('/build/') || /\.(png|jpe?g|webp|svg|woff2?)\$/.test(url.pathname)) {
    event.respondWith(caches.open(CACHE).then((c) => c.match(req).then((hit) => {
      const net = fetch(req).then((res) => { if (res.ok) { c.put(req, res.clone()); } return res; }).catch(() => hit);
      return hit || net;
    })));
  }
});
JS;

        return response($js, 200, ['Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'no-cache', 'Service-Worker-Allowed' => $base.'/']);
    }

    /** The menu's own path: /r/{slug} on the platform domain, empty on the restaurant's own domain. */
    private function base(Request $request): string
    {
        return $request->route('restaurant') !== null ? '/'.config('tenancy.path_prefix').'/'.$request->route('restaurant') : '';
    }
}
