<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Marketing\Jobs\SendCampaign;
use App\Modules\Marketing\Mail\CampaignMail;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\CampaignRecipient;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/** Builds the audience of a campaign and sends it, within the daily cap and only to people who agreed. */
class CampaignService
{
    public function __construct(private readonly MarketingSettings $settings) {}

    /** Guests who said yes to marketing, have an address, did not unsubscribe, and ordered often enough. */
    public function audience(Campaign $campaign): Builder
    {
        return Customer::query()->where('marketing_opt_in', true)->whereNull('unsubscribed_at')->whereNotNull('email')
            ->where('orders_count', '>=', (int) $campaign->min_orders);
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

        $this->audience($campaign)->orderBy('id')->chunkById(200, function ($customers) use ($campaign) {
            foreach ($customers as $customer) {
                CampaignRecipient::firstOrCreate(['campaign_id' => $campaign->id, 'customer_id' => $customer->id]);
            }
        });

        $room = $this->remainingToday($restaurant);

        CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'queued')->with('customer')->orderBy('id')->chunkById(100, function ($rows) use ($restaurant, $campaign, &$room) {
            foreach ($rows as $row) {
                $customer = $row->customer;

                // Consent is checked again at the moment of sending: they may have unsubscribed since the campaign started.
                if (! $customer || ! $customer->canBeEmailed()) {
                    $row->update(['status' => 'skipped']);

                    continue;
                }

                if ($room <= 0) {
                    $row->update(['status' => 'skipped']);

                    continue;
                }

                try {
                    Mail::to($customer->email)->send(new CampaignMail($campaign, $restaurant, $customer));
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

    /** A copy to the person writing the campaign, with the real layout and a working unsubscribe link. */
    public function sendTest(Restaurant $restaurant, Campaign $campaign, string $email, ?string $name): void
    {
        $sample = new Customer(['name' => $name, 'email' => $email, 'locale' => $restaurant->locale]);
        $sample->id = 0;

        Mail::to($email)->send(new CampaignMail($campaign, $restaurant, $sample, preview: true));
    }
}
