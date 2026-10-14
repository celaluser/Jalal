<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\CustomerSegment;
use App\Modules\Marketing\Services\CampaignService;
use App\Modules\Marketing\Services\MarketingSettings;
use App\Modules\Marketing\Services\Segments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** E-mail campaigns to guests who agreed to hear from the restaurant. */
class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns) {}

    public function index(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('marketing::campaigns.index', [
            'campaigns' => Campaign::orderByDesc('id')->paginate(20), 'remaining' => $this->campaigns->remainingToday($restaurant),
            'audience' => $this->campaigns->audience(new Campaign(['min_orders' => 0]))->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('marketing::campaigns.form', ['campaign' => null] + $this->context($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $campaign = Campaign::create($this->validated($request));

        return redirect()->route('campaigns.show', $campaign->id)->with('status', __('marketing.campaign_saved'));
    }

    public function show(Request $request, int $campaign): View
    {
        $model = Campaign::findOrFail($campaign);

        return view('marketing::campaigns.show', ['variants' => $model->ab_test ? $this->campaigns->variantStats($model) : null, 'opens' => $model->channel === 'email' && $model->track_opens ? $this->campaigns->openStats($model) : null, 'campaign' => $model, 'audience' => $this->campaigns->audience($model)->count(), 'remaining' => $this->campaigns->remainingToday($request->user()->restaurant), 'cap' => app(MarketingSettings::class)->dailyCap($request->user()->restaurant)]);
    }

    public function edit(Request $request, int $campaign): View
    {
        $model = $this->draft($campaign);

        return view('marketing::campaigns.form', ['campaign' => $model] + $this->context($request));
    }

    public function update(Request $request, int $campaign): RedirectResponse
    {
        $this->draft($campaign)->update($this->validated($request));

        return redirect()->route('campaigns.show', $campaign)->with('status', __('marketing.campaign_saved'));
    }

    public function destroy(int $campaign): RedirectResponse
    {
        Campaign::findOrFail($campaign)->delete();

        return redirect()->route('campaigns.index')->with('status', __('marketing.campaign_deleted'));
    }

    public function send(Request $request, int $campaign): RedirectResponse
    {
        try {
            $this->campaigns->start($request->user()->restaurant, $this->draft($campaign));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['campaign' => $e->getMessage() === 'ab_small' ? __('campaign_ab.error_small', ['min' => CampaignService::MIN_TEST_AUDIENCE]) : __('marketing.campaign_error_'.$e->getMessage())]);
        }

        return redirect()->route('campaigns.show', $campaign)->with('status', __('marketing.campaign_sending'));
    }

    /** Ends the waiting early: send subject A or B to everyone who has not had the campaign yet. */
    public function winner(Request $request, int $campaign): RedirectResponse
    {
        $variant = $request->validate(['variant' => ['required', 'in:A,B']])['variant'];
        $picked = $this->campaigns->pickWinner($request->user()->restaurant, Campaign::findOrFail($campaign), $variant);

        return redirect()->route('campaigns.show', $campaign)->with($picked ? 'status' : 'error', $picked ? __('campaign_ab.winner_sent', ['variant' => $picked]) : __('campaign_ab.not_testing'));
    }

    public function test(Request $request, int $campaign): RedirectResponse
    {
        $user = $request->user();
        $this->campaigns->sendTest($user->restaurant, Campaign::findOrFail($campaign), $user->email, $user->name);

        return back()->with('status', __('marketing.test_sent', ['email' => $user->email]));
    }

    private function draft(int $id): Campaign
    {
        $campaign = Campaign::findOrFail($id);
        abort_unless($campaign->status === Campaign::DRAFT, 403);

        return $campaign;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $email = $request->input('channel', 'email') === 'email';
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'subject' => [$email ? 'required' : 'nullable', 'string', 'max:160'],
            'channel' => ['nullable', 'in:email,sms,whatsapp,push'], 'segment' => ['nullable', Rule::in(array_merge(Segments::ALL, CustomerSegment::pluck('id')->map(fn ($id) => 'custom:'.$id)->all()))],
            'body' => ['required', 'string', 'max:'.($email ? 10000 : ($request->input('channel') === 'push' ? 180 : 600))], 'min_orders' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'ab_test' => ['nullable', 'boolean'], 'subject_b' => [$email && $request->boolean('ab_test') ? 'required' : 'nullable', 'string', 'max:160'],
            'test_percent' => ['nullable', 'integer', 'min:10', 'max:50'], 'winner_hours' => ['nullable', 'integer', 'min:1', 'max:72'], 'track_opens' => ['nullable', 'boolean'],
        ]);

        // SMS and WhatsApp have no subject line; the column still needs something to show in lists.
        return [
            'subject' => $data['subject'] ?? $data['name'], 'min_orders' => (int) ($data['min_orders'] ?? 0), 'channel' => $data['channel'] ?? 'email', 'segment' => $data['segment'] ?? 'all',
            // Subject tests and open tracking only exist for e-mail.
            'ab_test' => $email && $request->boolean('ab_test'), 'subject_b' => $email && $request->boolean('ab_test') ? trim((string) $data['subject_b']) : null,
            'test_percent' => (int) ($data['test_percent'] ?? 20), 'winner_hours' => (int) ($data['winner_hours'] ?? 4), 'track_opens' => $email && $request->boolean('track_opens', true),
        ] + collect($data)->except(['ab_test', 'subject_b', 'test_percent', 'winner_hours', 'track_opens'])->all();
    }

    /** @return array<string, mixed> */
    private function context(Request $request): array
    {
        return ['audience' => $this->campaigns->audience(new Campaign(['min_orders' => 0]))->count(), 'restaurant' => $request->user()->restaurant, 'customSegments' => CustomerSegment::orderBy('name')->get()];
    }
}
