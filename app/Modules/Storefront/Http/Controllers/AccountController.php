<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Services\CustomerService;
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Orders\Models\Order;
use App\Modules\Storefront\Services\MenuLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Optional guest account, no password: a guest who ordered with an e-mail address asks for a sign-in link,
 * then sees their past orders and keeps their details filled in. The page never says whether an address is known.
 */
class AccountController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly MenuLocale $locales, private readonly ThemeRegistry $themes) {}

    public static function sessionKey(int $restaurantId): string
    {
        return "guest_customer.{$restaurantId}";
    }

    /** The signed-in guest of this restaurant, if any. */
    public static function current(Request $request, int $restaurantId): ?Customer
    {
        $id = $request->session()->get(self::sessionKey($restaurantId));

        return $id ? Customer::find($id) : null;
    }

    public function show(Request $request): View
    {
        $restaurant = $this->tenant->get();
        $locale = $this->locales->resolve($request, $restaurant);
        app()->setLocale($locale);
        $customer = self::current($request, $restaurant->id);

        return view('storefront::account', [
            'restaurant' => $restaurant, 'locale' => $locale, 'dir' => $this->locales->isRtl($locale) ? 'rtl' : 'ltr', 'themeCss' => $this->themes->css($restaurant),
            'base' => $this->base($request), 'customer' => $customer,
            'orders' => $customer ? Order::where('customer_id', $customer->id)->latest('id')->limit(20)->get() : collect(),
        ]);
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $restaurant = $this->tenant->get();
        $locale = $this->locales->resolve($request, $restaurant);
        app()->setLocale($locale);
        $email = mb_strtolower(trim((string) $request->validate(['email' => ['required', 'email', 'max:190']])['email']));
        $customer = Customer::where('email', $email)->first();

        if ($customer) {
            $link = URL::temporarySignedRoute($this->route($request, 'account.verify'), now()->addMinutes(30), $this->params($request, ['customer' => $customer->id]));
            SafeMail::send($email, new TemplatedMail('account_login', ['name' => $customer->displayName(), 'restaurant' => $restaurant->name, 'login_url' => $link, 'minutes' => '30'], $locale));
        }

        // Same answer either way, so the form cannot be used to find out who ordered here.
        return back()->with('link_sent', true);
    }

    public function verify(Request $request): RedirectResponse
    {
        $restaurant = $this->tenant->get();
        abort_unless($request->hasValidSignature(), 403);
        $customer = Customer::findOrFail((int) $request->route('customer'));

        $request->session()->regenerate();
        $request->session()->put(self::sessionKey($restaurant->id), $customer->id);

        return redirect($this->base($request).'/account');
    }

    public function update(Request $request): RedirectResponse
    {
        $customer = $this->mustBeSignedIn($request);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:80'], 'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s.]{6,40}$/'],
            'birth_month' => ['nullable', 'integer', 'between:1,12'], 'birth_day' => ['nullable', 'integer', 'between:1,31'],
        ]);

        // Day and month go together, and must exist on a calendar (29 February is allowed; 31 April is not).
        $month = $data['birth_month'] ?? null;
        $day = $data['birth_day'] ?? null;

        if (($month === null) !== ($day === null) || ($month !== null && ! checkdate((int) $month, (int) $day, 2000))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['birth_day' => __('customer.birthday_invalid')]);
        }

        $customer->forceFill(['name' => $data['name'] ?? null, 'phone' => $data['phone'] ?? null, 'birth_month' => $month, 'birth_day' => $day])->save();

        return back()->with('status', __('customer.account_saved'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(self::sessionKey($this->tenant->get()->id));

        return redirect($this->base($request).'/account');
    }

    /** Right of access: everything this restaurant holds about the signed-in guest, as a JSON download. */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $customer = $this->mustBeSignedIn($request);
        $orders = Order::with('items')->where('customer_id', $customer->id)->orderBy('id')->get();
        $data = [
            'restaurant' => $this->tenant->get()->name, 'exported_at' => now()->toIso8601String(),
            'profile' => $customer->only(['name', 'email', 'phone', 'birth_month', 'birth_day', 'locale', 'marketing_opt_in', 'opted_in_at', 'unsubscribed_at', 'first_order_at', 'last_order_at', 'orders_count']),
            'orders' => $orders->map(fn (Order $o) => [
                'number' => $o->number, 'placed_at' => $o->created_at->toIso8601String(), 'status' => $o->status, 'type' => $o->type, 'total_cents' => $o->total_cents, 'currency' => $o->currency_code,
                'delivery_address' => $o->delivery_address, 'note' => $o->note, 'items' => $o->items->map(fn ($i) => ['name' => $i->name, 'qty' => $i->qty, 'total_cents' => $i->total_cents])->all(),
            ])->all(),
            'reviews' => \App\Modules\Marketing\Models\Review::where('customer_id', $customer->id)->get(['rating', 'nps', 'comment', 'created_at'])->all(),
        ];

        return response()->streamDownload(fn () => print json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'my-data.json', ['Content-Type' => 'application/json']);
    }

    /** Right to erasure, from the guest themself. */
    public function destroy(Request $request): RedirectResponse
    {
        $customer = $this->mustBeSignedIn($request);
        app(CustomerService::class)->forget($customer);
        $request->session()->forget(self::sessionKey($this->tenant->get()->id));

        return redirect($this->base($request).'/account')->with('status', __('customer.account_deleted'));
    }

    private function mustBeSignedIn(Request $request): Customer
    {
        $customer = self::current($request, $this->tenant->get()->id);
        abort_unless($customer, 403);

        return $customer;
    }

    private function base(Request $request): string
    {
        return $request->route('restaurant') !== null ? '/'.config('tenancy.path_prefix').'/'.$request->route('restaurant') : '';
    }

    /** Route name for the platform-domain or the own-domain variant, matching the one this request came in on. */
    private function route(Request $request, string $name): string
    {
        return ($request->route('restaurant') !== null ? 'storefront.' : 'storefront.host.').$name;
    }

    /** @param array<string, mixed> $extra */
    private function params(Request $request, array $extra): array
    {
        return $request->route('restaurant') !== null ? ['restaurant' => $request->route('restaurant')] + $extra : $extra;
    }
}
