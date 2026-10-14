<?php

return [
    'title' => 'API और वेबहुक', 'sub' => 'अपनी वेबसाइट, POS, डिलीवरी टूल, Zapier या Make को जोड़ें। दस्तावेज़: docs/API.md।',
    'not_included' => 'आपके प्लान में API और वेबहुक शामिल नहीं हैं।', 'upgrade' => 'प्लान अपग्रेड करें',
    'tokens' => 'API टोकन', 'tokens_help' => 'टोकन को "Authorization: Bearer …" के रूप में भेजें। हर टूल को केवल वही अनुमतियाँ दें जिनकी उसे ज़रूरत है।',
    'token_name' => 'नाम', 'token_days' => 'समाप्ति (दिनों बाद)', 'token_days_help' => 'खाली = कभी नहीं।', 'token_create' => 'टोकन बनाएँ',
    'new_token' => 'अपना टोकन अभी कॉपी करें। यह केवल एक बार दिखाया जाता है।', 'token_revoked' => 'टोकन रद्द कर दिया गया।', 'too_many_tokens' => 'आपके अधिकतम 20 टोकन हो सकते हैं।',
    'ability_menu:read' => 'मेनू पढ़ें', 'ability_menu:write' => 'कीमतें और उपलब्धता बदलें', 'ability_orders:read' => 'ऑर्डर पढ़ें', 'ability_orders:write' => 'ऑर्डर दें और ऑर्डर की स्थिति बदलें', 'ability_reservations:read' => 'रिज़र्वेशन पढ़ें', 'ability_reservations:write' => 'रिज़र्वेशन बनाएँ और बदलें', 'ability_customers:read' => 'ग्राहक पढ़ें',
    'last_used' => 'आखिरी उपयोग :time', 'never_used' => 'कभी उपयोग नहीं हुआ', 'expires' => ':date को समाप्त', 'revoke' => 'रद्द करें', 'revoke_confirm' => 'यह टोकन रद्द करें? इसका उपयोग करने वाले टूल काम करना बंद कर देंगे।',
    'webhooks' => 'वेबहुक', 'webhooks_help' => 'कुछ होने पर हम आपके HTTPS पते पर एक हस्ताक्षरित JSON संदेश POST करते हैं। X-Webhook-Signature हेडर सत्यापित करें (docs/API.md देखें)।',
    'webhook_url' => 'HTTPS पता', 'webhook_events' => 'इवेंट', 'webhook_add' => 'वेबहुक जोड़ें', 'event_*' => 'सब कुछ',
    'event_order.created' => 'ऑर्डर दिया गया', 'event_order.status_changed' => 'ऑर्डर की स्थिति बदली', 'event_order.paid' => 'ऑर्डर का भुगतान हुआ', 'event_reservation.created' => 'रिज़र्वेशन किया गया', 'event_reservation.status_changed' => 'रिज़र्वेशन की स्थिति बदली', 'event_ping' => 'टेस्ट',
    'new_secret' => ':url के लिए साइनिंग सीक्रेट। इसे अभी कॉपी करें; यह केवल एक बार दिखाया जाता है।', 'url_not_allowed' => 'यह पता उपयोग नहीं किया जा सकता। यह एक सार्वजनिक HTTPS पता होना चाहिए।',
    'too_many_endpoints' => 'आपके अधिकतम 10 वेबहुक हो सकते हैं।', 'endpoint_deleted' => 'वेबहुक हटा दिया गया।', 'test_sent' => 'टेस्ट संदेश कतार में जोड़ा गया।', 'send_test' => 'टेस्ट भेजें',
    'disabled_after_failures' => 'बार-बार विफल होने के बाद बंद कर दिया गया। पता ठीक करें, फिर इसे दोबारा चालू करें।', 'deliveries_empty' => 'अभी तक कोई डिलीवरी नहीं।',
    'status_sent' => 'पहुँचाया गया', 'status_failed' => 'विफल', 'status_pending' => 'प्रतीक्षा में', 'turn_on' => 'चालू करें', 'turn_off' => 'बंद करें', 'delete_confirm' => 'यह वेबहुक हटाएँ?',
];
