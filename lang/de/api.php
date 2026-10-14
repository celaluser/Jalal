<?php

return [
    'title' => 'API & Webhooks', 'sub' => 'Verbinden Sie Ihre eigene Website, Ihr POS, Lieferdienste, Zapier oder Make. Dokumentation: docs/API.md.',
    'not_included' => 'Ihr Tarif enthält keine API und keine Webhooks.', 'upgrade' => 'Tarif upgraden',
    'tokens' => 'API-Tokens', 'tokens_help' => 'Senden Sie den Token als "Authorization: Bearer …". Geben Sie jedem Tool nur die Berechtigungen, die es braucht.',
    'token_name' => 'Name', 'token_days' => 'Läuft ab nach (Tagen)', 'token_days_help' => 'Leer = nie.', 'token_create' => 'Token erstellen',
    'new_token' => 'Kopieren Sie Ihren Token jetzt. Er wird nur einmal angezeigt.', 'token_revoked' => 'Token widerrufen.', 'too_many_tokens' => 'Sie können höchstens 20 Tokens haben.',
    'ability_menu:read' => 'Speisekarte lesen', 'ability_menu:write' => 'Preise und Verfügbarkeit ändern', 'ability_orders:read' => 'Bestellungen lesen', 'ability_orders:write' => 'Bestellungen aufgeben und Status ändern', 'ability_reservations:read' => 'Reservierungen lesen', 'ability_reservations:write' => 'Reservierungen erstellen und ändern', 'ability_customers:read' => 'Kunden lesen',
    'last_used' => 'Zuletzt verwendet :time', 'never_used' => 'Nie verwendet', 'expires' => 'läuft ab am :date', 'revoke' => 'Widerrufen', 'revoke_confirm' => 'Diesen Token widerrufen? Tools, die ihn verwenden, funktionieren dann nicht mehr.',
    'webhooks' => 'Webhooks', 'webhooks_help' => 'Wir senden eine signierte JSON-Nachricht per POST an Ihre HTTPS-Adresse, wenn etwas passiert. Prüfen Sie den Header X-Webhook-Signature (siehe docs/API.md).',
    'webhook_url' => 'HTTPS-Adresse', 'webhook_events' => 'Ereignisse', 'webhook_add' => 'Webhook hinzufügen', 'event_*' => 'Alles',
    'event_order.created' => 'Bestellung aufgegeben', 'event_order.status_changed' => 'Bestellstatus geändert', 'event_order.paid' => 'Bestellung bezahlt', 'event_reservation.created' => 'Reservierung erstellt', 'event_reservation.status_changed' => 'Reservierungsstatus geändert', 'event_ping' => 'Test',
    'new_secret' => 'Signaturschlüssel für :url. Kopieren Sie ihn jetzt; er wird nur einmal angezeigt.', 'url_not_allowed' => 'Diese Adresse kann nicht verwendet werden. Sie muss eine öffentliche HTTPS-Adresse sein.',
    'too_many_endpoints' => 'Sie können höchstens 10 Webhooks haben.', 'endpoint_deleted' => 'Webhook gelöscht.', 'test_sent' => 'Testnachricht eingereiht.', 'send_test' => 'Test senden',
    'disabled_after_failures' => 'Nach wiederholten Fehlern ausgeschaltet. Korrigieren Sie die Adresse und schalten Sie ihn wieder ein.', 'deliveries_empty' => 'Noch keine Zustellungen.',
    'status_sent' => 'Zugestellt', 'status_failed' => 'Fehlgeschlagen', 'status_pending' => 'Wartet', 'turn_on' => 'Einschalten', 'turn_off' => 'Ausschalten', 'delete_confirm' => 'Diesen Webhook löschen?',
];
