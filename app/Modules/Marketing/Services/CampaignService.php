<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Jobs\SendCampaign;
use App\Modules\Marketing\Mail\CampaignMail;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\CampaignRecipient;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Models\PushSubscriber;
use App\Modules\Messaging\Services\Messenger;
use App\Modules\Orders\Services\PushNotifier;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Throwable;

/** Builds the audience of a campaign and sends it, within the daily cap and only to people who agreed. */
class CampaignService
{
    /** Fewest people for which a subject test makes sense. */
    public const MIN_TEST_AUDIENCE = 10;

    public function __construct(private readonly MarketingSettings $settings, private readonly Segments $segments, private readonly Messenger $messenger) {}

    /** Guests who said yes to marketing, can be reached on the campaign's channel, did not unsubscribe, and are in its segment. */
    public function audience(Campaign $campaign, ?Restaurant $restaurant = null): Builder
    {
        $restaurant ??= app(TenantContext::class)->get();

        // Push goes to browsers that allowed it, not to customer records.
        if (($campaign->channel ?? 'email') === 'push') {
            return PushSubscriber::query();
        }

        $query = Customer::query()->where('marketing_opt_in', true)->whereNull('unsubscribed_at')
            ->where('orders_count', '>=', (int) $campaign->min_orders);

        // SMS and WhatsApp need a phone number, e-mail needs an address.
        ($campaign->channel ?? 'email') === 'email' ? $query->whereNotNull('email') : $query->whereNotNull('phone');

        return $this->segments->scope($query, (string) ($campaign->segment ?? 'all'), $restaurant);
    }

    /** How many marketing e-mails this restaurant already sent today. */
    public function sentToday(Restaurant $restaurant): int
    {
        return CampaignRecipient::where('status', 'sent')->where('sent_at', '>=', now()->startOfDay())
            ->whereIn('campaign_id', Campaign::where('restaurant_id', $restaurant->id)->select('id'))->count();
    }

    public function remainingToday(Restaurant $restaurant): int
    {
        return max(0, $this->settings->dailyCap($restaurant) - $this->sentToday($restaurant));
    }

    /** Queues the campaign. @throws InvalidArgumentException code: sent | empty */
    public function start(Restaurant $restaurant, Campaign $campaign): void
    {
        if ($campaign->status !== Campaign::DRAFT) {
            throw new InvalidArgumentException('sent');
        }

        $audience = $this->audience($campaign)->count();

        if ($audience === 0) {
            throw new InvalidArgumentException('empty');
        }

        // A subject test needs enough people on each side to mean anything.
        if ($this->isTest($campaign) && $audience < self::MIN_TEST_AUDIENCE) {
            throw new InvalidArgumentException('ab_small');
        }

        $campaign->forceFill(['status' => Campaign::SENDING])->save();
        SendCampaign::dispatch($restaurant->id, $campaign->id);
    }

    /** Sends the queued campaign now. Safe to run again: only recipients still waiting are processed. */
    public function deliver(Restaurant $restaurant, Campaign $campaign): void
    {
        if ($campaign->status !== Campaign::SENDING) {
            return;
        }

        if ($campaign->channel === 'push') {
            $this->deliverPush($restaurant, $campaign);

            return;
        }

        $this->audience($campaign, $restaurant)->orderBy('id')->chunkById(200, function ($customers) use ($campaign) {
            foreach ($customers as $customer) {
                CampaignRecipient::firstOrCreate(['campaign_id' => $campaign->id, 'customer_id' => $customer->id]);
            }
        });

        // A subject test sends to a small group first (half A, half B); the rest wait ("held") for the winner.
        if ($this->isTest($campaign) && $campaign->winner === null && $campaign->test_sent_at === null) {
            $this->assignVariants($campaign);
        }

        $room = $this->remainingToday($restaurant);

        CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'queued')->with('customer')->orderBy('id')->chunkById(100, function ($rows) use ($restaurant, $campaign, &$room) {
            foreach ($rows as $row) {
                $customer = $row->customer;

                // Consent is checked again at the moment of sending: they may have unsubscribed since the campaign started.
                $email = ($campaign->channel ?? 'email') === 'email';

                if (! $customer || ! $customer->marketing_opt_in || $customer->unsubscribed_at !== null || ($email ? $customer->email === null : $customer->phone === null)) {
                    $row->update(['status' => 'skipped']);

                    continue;
                }

                if ($room <= 0) {
                    $row->update(['status' => 'skipped']);

                    continue;
                }

                try {
                    if ($email) {
                        Mail::to($customer->email)->send(new CampaignMail($campaign, $restaurant, $customer, variant: $row->variant, recipientId: $row->id));
                    } elseif (! $this->messenger->send($restaurant, $campaign->channel, (string) $customer->phone, $this->text($campaign, $restaurant, $customer), 'campaign')) {
                        $row->update(['status' => 'failed']); // no provider, bad number or the monthly message allowance is used

                        continue;
                    }

                    $row->update(['status' => 'sent', 'sent_at' => now()]);
                    $room--;
                } catch (Throwable $e) {
                    report($e);
                    $row->update(['status' => 'failed']);
                }
            }
        });

        $counts = CampaignRecipient::where('campaign_id', $campaign->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        // The test group is out; the rest waits for the winner (picked after a few hours, or by hand).
        if ($this->isTest($campaign) && $campaign->winner === null && ($counts['held'] ?? 0) > 0) {
            $campaign->forceFill([
                'status' => Campaign::TESTING, 'test_sent_at' => now(), 'recipients_count' => (int) $counts->sum(),
                'sent_count' => (int) ($counts['sent'] ?? 0), 'skipped_count' => (int) (($counts['skipped'] ?? 0) + ($counts['failed'] ?? 0)),
            ])->save();

            return;
        }

        $campaign->forceFill([
            'status' => Campaign::SENT, 'sent_at' => now(), 'recipients_count' => (int) $counts->sum(),
            'sent_count' => (int) ($counts['sent'] ?? 0), 'skipped_count' => (int) (($counts['skipped'] ?? 0) + ($counts['failed'] ?? 0)),
        ])->save();
    }

    private function deliverPush(Restaurant $restaurant, Campaign $campaign): void
    {
        $notifier = app(PushNotifier::class);
        $total = $sent = 0;

        PushSubscriber::query()->orderBy('id')->chunkById(200, function ($subs) use ($notifier, $campaign, $restaurant, &$total, &$sent) {
            $result = $notifier->broadcast($subs->map->only(['endpoint', 'p256dh', 'auth'])->all(), (string) ($campaign->name ?: $restaurant->name), (string) $campaign->body, $restaurant->publicUrl());
            $total += $subs->count();
            $sent += $result['sent'];

            if ($result['gone'] !== []) {
                PushSubscriber::whereIn('endpoint_hash', array_map(fn ($e) => hash('sha256', $e), $result['gone']))->delete();
            }
        });

        $campaign->forceFill(['status' => Campaign::SENT, 'sent_at' => now(), 'recipients_count' => $total, 'sent_count' => $sent, 'skipped_count' => $total - $sent])->save();
    }

    public function isTest(Campaign $campaign): bool
    {
        return $campaign->ab_test && ($campaign->channel ?? 'email') === 'email' && trim((string) $campaign->subject_b) !== '';
    }

    /** Splits the waiting recipients: a test group, half on subject A and half on B, the rest held back. Safe to run twice. */
    private function assignVariants(Campaign $campaign): void
    {
        if (CampaignRecipient::where('campaign_id', $campaign->id)->whereNotNull('variant')->exists()) {
            return;
        }

        $ids = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'queued')->pluck('id')->shuffle()->values();
        $each = max(1, intdiv((int) round($ids->count() * max(10, min(50, (int) $campaign->test_percent)) / 100), 2));

        CampaignRecipient::whereIn('id', $ids->slice(0, $each)->all())->update(['variant' => 'A']);
        CampaignRecipient::whereIn('id', $ids->slice($each, $each)->all())->update(['variant' => 'B']);
        CampaignRecipient::whereIn('id', $ids->slice($each * 2)->all())->update(['status' => 'held']);
    }

    /**
     * Opens per subject: how many were sent, how many opened, and the rate.
     *
     * @return array<string, array{sent: int, opened: int, rate: float}>
     */
    public function variantStats(Campaign $campaign): array
    {
        $stats = [];

        foreach (['A', 'B'] as $v) {
            $q = CampaignRecipient::where('campaign_id', $campaign->id)->where('variant', $v)->where('status', 'sent');
            $sent = (clone $q)->count();
            $opened = (clone $q)->whereNotNull('opened_at')->count();
            $stats[$v] = ['sent' => $sent, 'opened' => $opened, 'rate' => $sent > 0 ? round($opened / $sent * 100, 1) : 0.0];
        }

        return $stats;
    }

    /** Opens of the whole campaign, for the results card. @return array{sent: int, opened: int, rate: float} */
    public function openStats(Campaign $campaign): array
    {
        $q = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'sent');
        $sent = (clone $q)->count();
        $opened = (clone $q)->whereNotNull('opened_at')->count();

        return ['sent' => $sent, 'opened' => $opened, 'rate' => $sent > 0 ? round($opened / $sent * 100, 1) : 0.0];
    }

    /**
     * Sends the better subject (or the one chosen by hand) to everyone who was held back. Does nothing unless the test is running.
     * The higher open rate wins; a tie goes to A.
     */
    public function pickWinner(Restaurant $restaurant, Campaign $campaign, ?string $force = null): ?string
    {
        $winner = DB::transaction(function () use ($campaign, $force) {
            $locked = Campaign::whereKey($campaign->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== Campaign::TESTING) {
                return null;
            }

            $stats = $this->variantStats($locked);
            $winner = in_array($force, ['A', 'B'], true) ? $force : ($stats['B']['rate'] > $stats['A']['rate'] ? 'B' : 'A');

            CampaignRecipient::where('campaign_id', $locked->id)->where('status', 'held')->update(['status' => 'queued', 'variant' => $winner]);
            $locked->forceFill(['winner' => $winner, 'status' => Campaign::SENDING])->save();

            return $winner;
        });

        if ($winner !== null) {
            SendCampaign::dispatch($restaurant->id, $campaign->id);
        }

        return $winner;
    }

    /** Tests whose waiting time is over. */
    public function dueForWinner(): Collection
    {
        return Campaign::where('status', Campaign::TESTING)->get()->filter(fn (Campaign $c) => $c->test_sent_at !== null && $c->test_sent_at->addHours(max(1, (int) $c->winner_hours))->isPast())->values();
    }

    /** The SMS / WhatsApp text: the campaign body with the guest's name, and a way to stop (reply STOP is the provider's job; the link is ours). */
    public function text(Campaign $campaign, Restaurant $restaurant, Customer $customer): string
    {
        $body = str_replace(['{{name}}', '{{restaurant}}'], [$customer->name ?: '', $restaurant->name], (string) $campaign->body);

        return trim($body)."\n".__('marketing.sms_stop', ['url' => URL::signedRoute('marketing.unsubscribe', ['customer' => $customer->id])]);
    }

    /** A copy to the person writing the campaign, with the real layout and a working unsubscribe link. */
    public function sendTest(Restaurant $restaurant, Campaign $campaign, string $email, ?string $name): void
    {
        $sample = new Customer(['name' => $name, 'email' => $email, 'locale' => $restaurant->locale]);
        $sample->id = 0;

        Mail::to($email)->send(new CampaignMail($campaign, $restaurant, $sample, preview: true));
    }
}
