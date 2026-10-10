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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Throwable;

/** Builds the audience of a campaign and sends it, within the daily cap and only to people who agreed. */
class CampaignService
{
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

        if ($this->audience($campaign)->count() === 0) {
            throw new InvalidArgumentException('empty');
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
                        Mail::to($customer->email)->send(new CampaignMail($campaign, $restaurant, $customer));
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
