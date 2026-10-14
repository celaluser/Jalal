<?php

return [
    'title' => 'API e webhook', 'sub' => 'Collega il tuo sito web, POS, strumenti di consegna, Zapier o Make. Documentazione: docs/API.md.',
    'not_included' => 'Il tuo piano non include l\'API e i webhook.', 'upgrade' => 'Passa a un piano superiore',
    'tokens' => 'Token API', 'tokens_help' => 'Invia il token come "Authorization: Bearer …". Dai a ogni strumento solo le autorizzazioni di cui ha bisogno.',
    'token_name' => 'Nome', 'token_days' => 'Scade dopo (giorni)', 'token_days_help' => 'Vuoto = mai.', 'token_create' => 'Crea token',
    'new_token' => 'Copia subito il tuo token. Viene mostrato una sola volta.', 'token_revoked' => 'Token revocato.', 'too_many_tokens' => 'Puoi avere al massimo 20 token.',
    'ability_menu:read' => 'Leggere il menu', 'ability_menu:write' => 'Modificare prezzi e disponibilità', 'ability_orders:read' => 'Leggere gli ordini', 'ability_orders:write' => 'Inserire ordini e cambiare lo stato degli ordini', 'ability_reservations:read' => 'Leggere le prenotazioni', 'ability_reservations:write' => 'Creare e modificare prenotazioni', 'ability_customers:read' => 'Leggere i clienti',
    'last_used' => 'Ultimo utilizzo :time', 'never_used' => 'Mai usato', 'expires' => 'scade il :date', 'revoke' => 'Revoca', 'revoke_confirm' => 'Revocare questo token? Gli strumenti che lo usano smettono di funzionare.',
    'webhooks' => 'Webhook', 'webhooks_help' => 'Inviamo un messaggio JSON firmato (POST) al tuo indirizzo HTTPS quando succede qualcosa. Verifica l\'intestazione X-Webhook-Signature (vedi docs/API.md).',
    'webhook_url' => 'Indirizzo HTTPS', 'webhook_events' => 'Eventi', 'webhook_add' => 'Aggiungi webhook', 'event_*' => 'Tutto',
    'event_order.created' => 'Ordine effettuato', 'event_order.status_changed' => 'Stato dell\'ordine cambiato', 'event_order.paid' => 'Ordine pagato', 'event_reservation.created' => 'Prenotazione effettuata', 'event_reservation.status_changed' => 'Stato della prenotazione cambiato', 'event_ping' => 'Prova',
    'new_secret' => 'Segreto di firma per :url. Copialo subito; viene mostrato una sola volta.', 'url_not_allowed' => 'Questo indirizzo non può essere usato. Deve essere un indirizzo HTTPS pubblico.',
    'too_many_endpoints' => 'Puoi avere al massimo 10 webhook.', 'endpoint_deleted' => 'Webhook eliminato.', 'test_sent' => 'Messaggio di prova in coda.', 'send_test' => 'Invia prova',
    'disabled_after_failures' => 'Disattivato dopo ripetuti errori. Correggi l\'indirizzo, poi riattivalo.', 'deliveries_empty' => 'Ancora nessun invio.',
    'status_sent' => 'Consegnato', 'status_failed' => 'Non riuscito', 'status_pending' => 'In attesa', 'turn_on' => 'Attiva', 'turn_off' => 'Disattiva', 'delete_confirm' => 'Eliminare questo webhook?',
];
