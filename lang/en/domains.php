<?php

return [
    'title' => 'Domains', 'subtitle' => 'The web addresses your guests can use to open your menu.',
    'default_address' => 'Always-on address', 'default_help' => 'This link always works, whatever else you set up. Print it on your QR codes.',
    'subdomain' => 'Subdomain', 'subdomain_help' => 'Letters, numbers and dashes, at least 3 characters.', 'subdomain_off' => 'Subdomains are not enabled on this platform.', 'subdomain_preview' => 'Your menu will open at',
    'custom_domain' => 'Your own domain', 'custom_help' => 'For example menu.yourrestaurant.com. Connect it in three steps.', 'custom_off' => 'Custom domains are not enabled on this platform.',
    'custom_upgrade' => 'Your plan does not include a custom domain.', 'upgrade' => 'Upgrade plan', 'remove_domain' => 'Leave the field empty and save to disconnect.',
    'save' => 'Save', 'status_verified' => 'Connected', 'status_pending' => 'Waiting for DNS', 'status_none' => 'Not set',
    'step1' => 'Enter the domain above and save.', 'step2' => 'At your domain provider, add this TXT record to prove it is yours:', 'step3' => 'Press “Check DNS”. DNS changes can take up to an hour to spread.',
    'record_type' => 'Type', 'record_host' => 'Host', 'record_value' => 'Value', 'cname_hint' => 'Also point the domain to us with a CNAME record to :target (or follow the instructions below).',
    'platform_instructions' => 'Instructions from the platform', 'check_dns' => 'Check DNS', 'copy' => 'Copy', 'copied' => 'Copied',
    'verified_now' => 'Your domain is connected.', 'not_found_yet' => 'We could not find the DNS record yet. Check it and try again in a few minutes.', 'verified_manual' => 'Domain verified',
    'error_upgrade' => 'Your own address is a store feature. Get it in the store first.', 'error_unavailable' => 'This is not available for your restaurant.', 'error_invalid' => 'That does not look like a valid address.', 'error_reserved' => 'That name is reserved. Choose another.',
    'error_taken' => 'That address is already used by another restaurant.', 'error_platform' => 'You cannot use the platform’s own domain here.',
];
