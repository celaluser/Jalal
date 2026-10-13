<?php

namespace App\Modules\Hub\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hub\Models\OrderChannel;
use App\Modules\Hub\Support\ChannelRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** The restaurant's list of order channels. The secret address is shown once, when a channel is made or its address renewed. */
class ChannelController extends Controller
{
    public function index(): View
    {
        return view('hub::index', [
            'channels' => OrderChannel::orderBy('id')->get(),
            'kinds' => collect(ChannelRegistry::all())->map(fn ($a) => $a->label())->all(),
            'secret' => session('channel_secret'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'kind' => ['required', 'in:'.implode(',', array_keys(ChannelRegistry::all()))]]);
        [$channel, $token] = $this->issue(new OrderChannel(['name' => trim($data['name']), 'kind' => $data['kind']]));

        return back()->with('status', __('hub.created'))->with('channel_secret', ['id' => $channel->id, 'url' => route('hub.receive', $token)]);
    }

    public function renew(OrderChannel $channel): RedirectResponse
    {
        [, $token] = $this->issue($channel);

        return back()->with('status', __('hub.renewed'))->with('channel_secret', ['id' => $channel->id, 'url' => route('hub.receive', $token)]);
    }

    public function toggle(OrderChannel $channel): RedirectResponse
    {
        $channel->update(['is_active' => ! $channel->is_active]);

        return back()->with('status', __('admin.saved'));
    }

    public function destroy(OrderChannel $channel): RedirectResponse
    {
        $channel->delete();

        return back()->with('status', __('hub.deleted'));
    }

    /** @return array{0: OrderChannel, 1: string} */
    private function issue(OrderChannel $channel): array
    {
        $token = Str::random(40);
        $channel->forceFill(['token_hash' => hash('sha256', $token), 'token_hint' => substr($token, 0, 6)])->save();

        return [$channel, $token];
    }
}
