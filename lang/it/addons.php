<?php

return [
    'title' => 'Add-on', 'sub' => 'Funzionalità extra fornite come pacchetti firmati. Hanno accesso completo all\'applicazione, quindi installa solo add-on da fonti di cui ti fidi.',
    'install' => 'Installa un add-on', 'install_help' => 'Carica il file .zip che hai ricevuto. Prima vengono controllati la firma e ogni file. I nuovi add-on partono disattivati.',
    'trust_warning' => 'Un add-on può leggere e modificare tutto, compreso il database. Fai prima un backup.', 'install_button' => 'Controlla e installa',
    'installed_title' => 'Add-on installati', 'installed' => ':name installato. Attivalo per iniziare a usarlo.', 'updated' => 'Add-on aggiornato.', 'removed' => 'Add-on rimosso. I suoi dati restano nel database.',
    'on' => 'Attivo', 'off' => 'Disattivo', 'turn_on' => 'Attiva', 'turn_off' => 'Disattiva', 'remove_confirm' => 'Rimuovere questo add-on? I suoi file vengono eliminati; le tabelle del database restano.',
    'empty' => 'Nessun add-on installato', 'empty_text' => 'Gli add-on che acquisti o crei appaiono qui.',
    'not_an_addon' => 'Questo pacchetto non è un add-on (ogni file deve trovarsi in addons/<name>/).', 'invalid_manifest' => 'La descrizione dell\'add-on (addon.json) manca o non è valida.',
    'needs_core' => 'Richiede la versione :version dell\'applicazione o successiva.', 'provider_missing' => 'Non è stato possibile caricare il codice dell\'add-on.', 'not_found' => 'Add-on non trovato.',
];
