<?php

return [
    'title' => 'API & webhooks', 'sub' => 'Connect your own website, POS, delivery tools, Zapier or Make. Documentation: docs/API.md.',
    'not_included' => 'Your plan does not include the API and webhooks.', 'upgrade' => 'Upgrade your plan',
    'tokens' => 'API tokens', 'tokens_help' => 'Send the token as "Authorization: Bearer …". Give each tool only the abilities it needs.',
    'token_name' => 'Name', 'token_days' => 'Expires after (days)', 'token_days_help' => 'Empty = never.', 'token_create' => 'Create token',
    'new_token' => 'Copy your token now. It is shown only once.', 'token_revoked' => 'Token revoked.', 'too_many_tokens' => 'You can have at most 20 tokens.',
    'ability_menu:read' => 'Read the menu', 'ability_menu:write' => 'Change prices and availability', 'ability_orders:read' => 'Read orders', 'ability_orders:write' => 'Place orders and change order status', 'ability_reservations:read' => 'Read reservations', 'ability_reservations:write' => 'Create and change reservations', 'ability_customers:read' => 'Read customers',
    'last_used' => 'Last used :time', 'never_used' => 'Never used', 'expires' => 'expires :date', 'revoke' => 'Revoke', 'revoke_confirm' => 'Revoke this token? Tools using it stop working.',
    'webhooks' => 'Webhooks', 'webhooks_help' => 'We POST a signed JSON message to your HTTPS address when something happens. Verify the X-Webhook-Signature header (see docs/API.md).',
    'webhook_url' => 'HTTPS address', 'webhook_events' => 'Events', 'webhook_add' => 'Add webhook', 'event_*' => 'Everything',
    'event_order.created' => 'Order placed', 'event_order.status_changed' => 'Order status changed', 'event_order.paid' => 'Order paid', 'event_reservation.created' => 'Reservation made', 'event_reservation.status_changed' => 'Reservation status changed', 'event_ping' => 'Test',
    'new_secret' => 'Signing secret for :url. Copy it now; it is shown only once.', 'url_not_allowed' => 'This address cannot be used. It must be a public HTTPS address.',
    'too_many_endpoints' => 'You can have at most 10 webhooks.', 'endpoint_deleted' => 'Webhook deleted.', 'test_sent' => 'Test message queued.', 'send_test' => 'Send test',
    'disabled_after_failures' => 'Switched off after repeated failures. Fix the address, then turn it back on.', 'deliveries_empty' => 'No deliveries yet.',
    'status_sent' => 'Delivered', 'status_failed' => 'Failed', 'status_pending' => 'Waiting', 'turn_on' => 'Turn on', 'turn_off' => 'Turn off', 'delete_confirm' => 'Delete this webhook?',
];
