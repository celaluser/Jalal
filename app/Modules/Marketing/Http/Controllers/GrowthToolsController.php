<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Services\MarketingSettings;
use App\Modules\Tables\Services\TableQr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Things a restaurant shares outside the menu: a printable flyer, an embeddable ordering button, and the link-in-bio page. */
class GrowthToolsController extends Controller
{
    /** Printable A5 flyer with the menu QR code. */
    public function flyer(Request $request, TableQr $qr): View
    {
        $restaurant = $request->user()->restaurant;

        return view('marketing::growth.flyer', ['restaurant' => $restaurant, 'qr' => $qr->svg($restaurant), 'url' => $restaurant->publicUrl(), 'headline' => $request->query('headline')]);
    }

    /** Snippets to paste on the restaurant's own website. */
    public function widget(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('marketing::growth.widget', ['restaurant' => $restaurant, 'script' => $restaurant->publicUrl('widget.js'), 'menu' => $restaurant->publicUrl()]);
    }

    /** Public: the floating "Order online" button script. Works on any site; the menu opens in an overlay. */
    public function script(TenantContext $tenant): Response
    {
        $restaurant = $tenant->get();
        $label = e(__('marketing.widget_button'));
        $url = json_encode($restaurant->publicUrl(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $restaurant->brandColor()) ? $restaurant->brandColor() : '#111111';

        $js = <<<JS
(function(){if(window.__qrmWidget)return;window.__qrmWidget=1;var u={$url},b=document.createElement('button');
b.textContent="{$label}";b.setAttribute('style','position:fixed;bottom:18px;inset-inline-end:18px;z-index:2147483000;background:{$color};color:#fff;border:0;border-radius:999px;padding:14px 22px;font:600 15px system-ui,sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.25);cursor:pointer');
b.onclick=function(){var o=document.createElement('div');o.setAttribute('style','position:fixed;inset:0;z-index:2147483001;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center');
o.innerHTML='<iframe src="'+u+'" style="border:0;width:min(480px,100vw);height:min(860px,100vh);background:#fff;border-radius:16px"></iframe>';
o.onclick=function(e){if(e.target===o)o.remove()};document.body.appendChild(o)};document.body.appendChild(b)})();
JS;

        return response($js, 200, ['Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** Public link-in-bio page: big buttons to the menu and the restaurant's channels. */
    public function links(TenantContext $tenant, MarketingSettings $settings): View
    {
        $restaurant = $tenant->get();
        $s = $settings->for($restaurant);

        $buttons = array_filter([
            ['key' => 'menu', 'url' => $restaurant->publicUrl()],
            $s['link_whatsapp'] ? ['key' => 'whatsapp', 'url' => 'https://wa.me/'.preg_replace('/\D+/', '', $s['link_whatsapp'])] : null,
            $s['link_phone'] ? ['key' => 'phone', 'url' => 'tel:'.preg_replace('/[^\d+]/', '', $s['link_phone'])] : null,
            $s['link_instagram'] ? ['key' => 'instagram', 'url' => $s['link_instagram']] : null,
            $s['link_facebook'] ? ['key' => 'facebook', 'url' => $s['link_facebook']] : null,
            $s['link_website'] ? ['key' => 'website', 'url' => $s['link_website']] : null,
            $s['review_url'] ? ['key' => 'review', 'url' => $s['review_url']] : null,
        ]);

        return view('marketing::growth.links', ['restaurant' => $restaurant, 'buttons' => $buttons]);
    }
}
