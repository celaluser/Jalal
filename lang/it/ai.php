<?php

return [
    'error_not_configured' => 'L\'IA non è ancora configurata. Chiedi all\'amministratore della piattaforma di aggiungere una chiave IA.',
    'error_no_credits' => 'Hai usato tutti i crediti IA del tuo piano per questo mese. Si rinnovano il 1° del mese, oppure puoi passare a un piano superiore.',
    'error_provider_error' => 'Il servizio IA non ha risposto. Riprova tra un attimo.',
    'error_rate_limited' => 'Il servizio IA è occupato al momento. Riprova tra un minuto.',
    'error_bad_response' => 'Questa volta l\'IA non è riuscita a produrre una risposta utilizzabile. Riprova o aggiungi più dettagli.',
    'error_limit_categories' => 'Importare questo menu supererebbe il limite di categorie del tuo piano.',
    'error_limit_products' => 'Importare questo menu supererebbe il limite di prodotti del tuo piano.',

    'write' => 'Scrivi con l\'IA', 'writing' => 'Scrittura in corso…', 'hints' => 'Ingredienti o note (facoltativo)', 'tone' => 'Stile',
    'tone_appetizing' => 'Appetitoso', 'tone_short' => 'Breve e semplice', 'tone_elegant' => 'Elegante', 'tone_playful' => 'Giocoso',
    'translate' => 'Traduci con l\'IA', 'translating' => 'Traduzione in corso…', 'translate_hint' => 'Compila i campi vuoti delle altre lingue partendo dalla tua lingua principale.',
    'suggest_tags' => 'Suggerisci allergeni con l\'IA', 'suggesting' => 'Controllo in corso…',
    'tags_notice' => 'Suggerito dall\'IA. Controlla tu stesso ogni allergene: gli ospiti si affidano a queste informazioni.', 'tags_none' => 'Non sono stati suggeriti allergeni o etichette dietetiche.',
    'tags_applied' => 'Selezionati: :list',
    'credits_left' => ':count crediti rimasti questo mese', 'credits_unlimited' => 'I crediti IA non sono limitati nel tuo piano', 'credits_cost' => 'Costa :count credito/i',
    'review_note' => 'Controlla il testo prima di salvare. L\'IA può sbagliare.',

    'import_title' => 'Importa un menu con l\'IA', 'import_sub' => 'Incolla il tuo menu da un PDF, un sito web o un documento. L\'IA lo organizza in categorie e piatti, e tu controlli tutto prima che venga aggiunto.',
    'import_paste' => 'Incolla il testo del tuo menu', 'import_placeholder' => "ANTIPASTI\nBruschetta - 7,50\nZuppa del giorno - 6\n\nSECONDI\nPizza margherita, pomodoro e basilico - 12", 'import_chars' => ':count di :max caratteri',
    'import_read' => 'Leggi il mio menu', 'import_cost' => 'Costa :count crediti', 'import_review' => 'Controlla', 'import_found' => ':count piatti trovati',
    'import_no_price' => 'Prezzo non trovato: questi piatti vengono aggiunti nascosti finché non imposti un prezzo.', 'import_add' => 'Aggiungi al mio menu', 'import_adding' => 'Aggiunta in corso…',
    'import_done' => 'Aggiunte :categories categorie e :products piatti. :hidden sono nascosti finché non hanno un prezzo.', 'import_remove' => 'Rimuovi', 'import_again' => 'Ricomincia',
    'import_not_configured_title' => 'L\'IA non è ancora disponibile', 'import_how' => 'Come funziona',
    'import_step1' => 'Incolla il testo del tuo menu.', 'import_step2' => 'L\'IA lo legge e lo raggruppa. Modifica nomi e prezzi se serve.', 'import_step3' => 'Aggiungilo al tuo menu con un clic.',
    'category' => 'Categoria', 'dish' => 'Nome del piatto', 'price' => 'Prezzo', 'description' => 'Descrizione',
    'button_import' => 'Importa con l\'IA',
    'need_name' => 'Inserisci prima il nome.',

    'import_photo' => 'Oppure una foto del menu', 'import_photo_cost' => 'Una foto costa :count crediti IA (ogni pagina, fino a 5, viene conteggiata). Funziona con menu stampati e screenshot.', 'import_pdf' => 'Oppure un menu in PDF', 'import_pdf_hint' => 'Solo PDF con testo reale. Per un PDF scansionato, scatta invece una foto della pagina.',
    'error_bad_file' => 'Non è stato possibile leggere quel file. Prova con un JPG, PNG o PDF nitido.', 'error_pdf_no_text' => 'Questo PDF non ha testo leggibile (probabilmente è una scansione). Usa invece l\'importazione da foto.',
    'bulk_title' => 'Traduci tutto il menu', 'bulk_text' => 'Compila in background ogni traduzione mancante per piatti e categorie. Le lingue che hai bloccato non vengono mai toccate. Ogni elemento usa crediti di traduzione.', 'bulk_start' => 'Traduci tutto ciò che manca',
    'bulk_running' => 'Traduzione in corso…', 'bulk_done' => 'Fatto: :n testi tradotti.', 'bulk_stopped' => 'Interrotto prima della fine (crediti esauriti o servizio IA non disponibile). Quanto già fatto viene mantenuto.',
    'draft_reply' => 'Bozza di risposta con l\'IA', 'draft_note' => 'Leggi e modifica la bozza prima di inviarla.', 'insights_title' => 'Idee dai tuoi numeri', 'insights_button' => 'Ottieni idee', 'insights_note' => 'Scritte da un\'IA in base ai dati qui sopra. Considerale suggerimenti.',
    'assistant_name' => 'Assistente del menu', 'assistant_ask' => 'Chiedi sul menu', 'assistant_placeholder' => 'C\'è qualcosa di vegetariano? Cosa si abbina all\'hamburger?', 'assistant_send' => 'Invia', 'assistant_hint' => 'Le risposte provengono solo da questo menu. Chiedi al personale di confermare le allergie.', 'assistant_error' => 'L\'assistente non è disponibile al momento.',
];
