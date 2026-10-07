<!DOCTYPE html>
<html>
<body style="margin:0;padding:24px;background:#f4f4f2;font-family:-apple-system,Segoe UI,Roboto,sans-serif;color:#222;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:12px;">
            <tr><td style="padding:20px 28px;border-bottom:1px solid #eee;font-size:18px;font-weight:700;color:#ea580c;"><a href="{{ $menuUrl }}" style="color:#ea580c;text-decoration:none;">{{ $restaurant->name }}</a></td></tr>
            <tr><td style="padding:24px 28px;font-size:15px;line-height:1.6;">{!! $body !!}</td></tr>
            <tr><td style="padding:16px 28px;border-top:1px solid #eee;font-size:12px;color:#888;">{{ __('marketing.unsub_footer', ['name' => $restaurant->name]) }}
                <a href="{{ $unsubscribeUrl }}" style="color:#888;">{{ __('marketing.unsub_link') }}</a></td></tr>
        </table>
    </td></tr></table>
</body>
</html>
