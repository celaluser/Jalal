<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\CampaignRecipient;
use Illuminate\Http\Response;

/** The 1x1 picture in a campaign e-mail. Loading it is what counts as an "open". The address is signed, so ids cannot be guessed. */
class OpenTrackingController extends Controller
{
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function pixel(int $recipient): Response
    {
        $row = CampaignRecipient::find($recipient);

        if ($row && $row->status === 'sent') {
            CampaignRecipient::whereKey($row->id)->update(['open_count' => $row->open_count + 1, 'opened_at' => $row->opened_at ?? now()]);
        }

        return response(base64_decode(self::GIF), 200, ['Content-Type' => 'image/gif', 'Cache-Control' => 'no-store, max-age=0', 'Content-Length' => strlen(base64_decode(self::GIF))]);
    }
}
