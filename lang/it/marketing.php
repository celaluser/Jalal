<?php

return [
    // Customers
    'customers_title' => 'Clienti', 'customers_sub' => 'Tutti coloro che hanno lasciato i propri dati con un ordine. Costruisci la fedeltà, individua gli habitué e scrivi a chi ha acconsentito.',
    'search_customers' => 'Cerca nome, email o telefono', 'filter_all' => 'Tutti', 'filter_opted' => 'Consenso al marketing', 'filter_repeat' => 'Habitué (2+ ordini)',
    'sort_recent' => 'Ultimo ordine', 'sort_orders' => 'Più ordini', 'sort_spent' => 'Chi spende di più', 'sort_name' => 'Nome',
    'col_customer' => 'Cliente', 'col_orders' => 'Ordini', 'col_spent' => 'Speso', 'col_last' => 'Ultimo ordine', 'col_marketing' => 'Marketing',
    'stat_all' => 'Clienti', 'stat_opted' => 'Raggiungibili via email', 'stat_repeat' => 'Habitué',
    'consent_yes' => 'Consenso dato', 'consent_no' => 'No', 'consent_unsub' => 'Disiscritto', 'anonymous' => 'Ospite', 'export' => 'Esporta CSV',
    'customers_empty' => 'Ancora nessun cliente', 'customers_empty_text' => 'Gli ospiti compaiono qui quando lasciano nome, telefono o email con un ordine.',
    'customer_since' => 'Cliente dal :date', 'agreed_on' => 'Consenso al marketing dato il :date', 'unsubscribed_on' => 'Disiscritto il :date', 'no_consent' => 'Non ha acconsentito alle email di marketing.',
    'notes' => 'Note private', 'notes_help' => 'Le vede solo il tuo team. Allergie, preferenze, tutto ciò che vale la pena ricordare.', 'order_history' => 'Ordini', 'no_orders' => 'Nessun ordine.',
    'delete_customer' => 'Elimina questo cliente', 'delete_help' => 'Rimuove la scheda e cancella nome, telefono, email e indirizzo dai suoi ordini. I dati di vendita restano. L\'operazione non può essere annullata.', 'delete_confirm' => 'Eliminare questo cliente e cancellare i suoi dati personali?',
    'customer_deleted' => 'Cliente eliminato e dati personali cancellati.', 'avg_order' => 'Ordine medio',

    // Promo codes
    'promos_title' => 'Codici promo', 'promos_sub' => 'Codici sconto che i tuoi ospiti inseriscono al pagamento. Da non confondere con i coupon di abbonamento della piattaforma.',
    'new_promo' => 'Nuovo codice', 'edit_promo' => 'Modifica codice', 'promo_code' => 'Codice', 'promo_desc' => 'Descrizione', 'promo_desc_help' => 'La vedi solo tu.',
    'promo_type' => 'Tipo di sconto', 'type_percent' => 'Sconto in percentuale', 'type_fixed' => 'Sconto in importo', 'promo_value' => 'Valore', 'promo_min' => 'Ordine minimo', 'promo_max_uses' => 'Utilizzi massimi', 'promo_max_help' => 'Lascia vuoto per illimitato.',
    'promo_starts' => 'Inizia', 'promo_ends' => 'Termina', 'promo_active' => 'Attivo', 'promo_uses' => 'Usato', 'promo_status' => 'Stato',
    'status_active' => 'Attivo', 'status_off' => 'Disattivato', 'status_expired' => 'Scaduto', 'status_scheduled' => 'Programmato', 'status_used_up' => 'Esaurito', 'reward_badge' => 'Premio fedeltà', 'reward_for' => 'Per :name',
    'promo_saved' => 'Codice promo salvato.', 'promo_deleted' => 'Codice promo eliminato.', 'promos_empty' => 'Ancora nessun codice promo', 'promos_empty_text' => 'Crea un codice come BENVENUTO10 e condividilo sui social o nelle tue campagne.',
    'turn_off' => 'Disattiva', 'turn_on' => 'Attiva', 'delete_promo_confirm' => 'Eliminare questo codice promo?', 'min_order_label' => 'da :amount',
    'reward_percent' => ':value% di sconto', 'reward_fixed' => ':amount di sconto',

    // Guest checkout
    'promo_field' => 'Codice promo', 'promo_apply' => 'Applica', 'promo_remove' => 'Rimuovi', 'promo_applied' => 'Codice :code applicato: :amount di sconto.', 'promo_discount' => 'Sconto',
    'opt_in' => 'Inviami offerte e novità di :name via email. Posso disiscrivermi in qualsiasi momento.',

    // Reviews
    'reviews_title' => 'Recensioni', 'reviews_sub' => 'Cosa hanno detto gli ospiti dopo il pasto.', 'reviews_empty' => 'Ancora nessuna recensione', 'reviews_empty_text' => 'Gli ospiti vengono invitati a valutare il loro ordine una volta completato.',
    'average' => 'Valutazione media', 'review_count' => '{1} 1 recensione|[2,*] :count recensioni', 'filter_low' => 'Valutazioni basse', 'filter_unanswered' => 'Senza risposta',
    'low_alert' => '{1} 1 valutazione bassa aspetta ancora una risposta.|[2,*] :count valutazioni basse aspettano ancora una risposta.', 'low_flag' => 'Richiede attenzione',
    'reply' => 'La tua risposta', 'reply_save' => 'Salva risposta', 'reply_help' => 'Mostrata all\'ospite nella pagina del suo ordine.', 'hide' => 'Nascondi dal menu', 'show' => 'Mostra nel menu', 'hidden' => 'Nascosta', 'order_ref' => 'Ordine :number',
    'public_note' => 'Visibile nel tuo menu', 'stars' => '{1} :count stella|[2,*] :count stelle su 5',

    // Guest review form
    'rate_title' => 'Com\'è andata?', 'rate_sub' => 'Valuta il tuo ordine. Bastano dieci secondi.', 'rate_comment' => 'Vuoi aggiungere qualcosa? (facoltativo)', 'rate_public' => 'Mostra la mia valutazione (con il mio nome) nel menu',
    'rate_send' => 'Invia la mia valutazione', 'rate_thanks' => 'Grazie per il tuo feedback!', 'rate_yours' => 'La tua valutazione', 'rate_reply' => 'Risposta del ristorante',
    'review_error_closed' => 'Le valutazioni non sono aperte per questo ordine.', 'review_error_done' => 'Hai già valutato questo ordine.', 'review_error_rating' => 'Scegli da 1 a 5 stelle.',
    'rating_on_menu' => ':average (:count)',

    // Loyalty & settings
    'loyalty_title' => 'Fedeltà e recensioni', 'loyalty_sub' => 'Premia automaticamente gli habitué e chiedi agli ospiti com\'è andata.',
    'loyalty_section' => 'Premio fedeltà', 'loyalty_enabled' => 'Premia gli ospiti abituali', 'loyalty_enabled_help' => 'Dopo ogni N-esimo ordine completato l\'ospite guadagna un codice promo monouso che solo lui può usare. Compare nella pagina del suo ordine e in un\'email.',
    'loyalty_every' => 'Premia dopo ogni … ordini completati', 'loyalty_type' => 'Premio', 'loyalty_value' => 'Valore', 'loyalty_percent_hint' => 'Percentuale dell\'ordine', 'loyalty_fixed_hint' => 'Importo nella tua valuta', 'loyalty_valid' => 'Valido per (giorni)',
    'reviews_section' => 'Recensioni', 'reviews_enabled' => 'Chiedi agli ospiti di valutare gli ordini completati', 'review_email' => 'Chiedi anche via email quando l\'ospite ha lasciato un indirizzo', 'show_rating' => 'Mostra la valutazione media nel menu pubblico',
    'messages_section' => 'Messaggi di testo', 'calling_code' => 'Prefisso internazionale del paese', 'calling_code_help' => 'Usato per inviare SMS e WhatsApp agli ospiti che hanno digitato un numero locale, ad esempio 39 per l\'Italia. Lascia vuoto per scrivere solo ai numeri che iniziano con +.',
    'email_section' => 'Invio email', 'daily_cap' => 'Email di campagna al giorno', 'daily_cap_help' => 'La piattaforma consente al massimo :max al giorno per ristorante. Mantiene in buono stato il tuo account di posta.',
    'reward_earned' => 'Hai guadagnato un premio!', 'reward_earned_text' => 'Grazie per aver ordinato ancora e ancora. Usa questo codice al tuo prossimo ordine:', 'reward_valid_until' => 'Valido fino al :date, una sola volta, solo per te.',
    'there' => 'ospite',

    // Campaigns
    'campaigns_title' => 'Campagne', 'campaigns_sub' => 'Scrivi via email agli ospiti che hanno acconsentito a ricevere tue comunicazioni. Ogni messaggio ha un link per disiscriversi.',
    'new_campaign' => 'Nuova campagna', 'edit_campaign' => 'Modifica campagna', 'campaign_name' => 'Nome', 'campaign_name_help' => 'Lo vedi solo tu.', 'campaign_subject' => 'Oggetto', 'campaign_body' => 'Messaggio',
    'campaign_body_help' => 'Testo semplice con formattazione di base (**grassetto**, [link](https://…)). Usa {{name}} e {{restaurant}} per personalizzare.', 'campaign_audience' => 'Chi lo riceve', 'min_orders' => 'Solo ospiti con almeno … ordini', 'min_orders_help' => '0 significa tutti quelli che hanno acconsentito.',
    'audience_now' => '{0} Nessuno può ancora riceverlo.|{1} 1 ospite lo riceverà.|[2,*] :count ospiti lo riceveranno.', 'audience_note' => 'Solo gli ospiti che hanno spuntato la casella email al pagamento, hanno un indirizzo e non si sono disiscritti.',
    'campaign_saved' => 'Campagna salvata.', 'campaign_deleted' => 'Campagna eliminata.', 'campaign_sending' => 'La tua campagna è in partenza. Può richiedere qualche minuto.',
    'campaign_error_sent' => 'Questa campagna è già stata inviata.', 'campaign_error_empty' => 'Nessuno può ancora ricevere questa campagna.',
    'status_draft' => 'Bozza', 'status_sending' => 'In invio', 'status_sent' => 'Inviata', 'campaigns_empty' => 'Ancora nessuna campagna', 'campaigns_empty_text' => 'Parla ai tuoi habitué di un nuovo piatto o di un\'offerta per i giorni tranquilli.',
    'sent_count' => 'Inviati', 'skipped_count' => 'Non inviati', 'recipients' => 'Destinatari', 'send_test' => 'Inviami una prova', 'send_now' => 'Invia campagna', 'send_confirm' => 'Inviare ora questa campagna? L\'operazione non può essere annullata.',
    'test_sent' => 'Email di prova inviata a :email.', 'today_room' => 'Oggi possono partire ancora :count email (limite :cap).', 'cap_note' => 'Gli ospiti oltre il limite di oggi vengono saltati. Invia di nuovo domani con una nuova campagna.', 'sent_on' => 'Inviata il :date',
    'preview' => 'Anteprima', 'unsub_footer' => 'Ricevi questo messaggio perché hai acconsentito a ricevere offerte da :name.', 'unsub_link' => 'Disiscriviti',

    // Unsubscribe page
    'unsub_title' => 'Disiscrizione', 'unsub_ask' => 'Interrompere le email da :name?', 'unsub_ask_text' => 'Non riceverai più offerte e novità. Gli aggiornamenti sugli ordini continueranno ad arrivarti.', 'unsub_button' => 'Sì, disiscrivimi',
    'unsub_done' => 'Sei disiscritto', 'unsub_done_text' => 'Non riceverai più email di marketing da :name.', 'unsub_already' => 'Sei già disiscritto da :name.',

    // Banners and pop-ups
    'banners_title' => 'Banner e pop-up', 'banners_sub' => 'Mostra un nuovo piatto, un\'offerta o un avviso in cima al tuo menu.', 'new_banner' => 'Nuovo banner', 'edit_banner' => 'Modifica banner',
    'banners_empty' => 'Ancora nessun banner', 'banners_empty_text' => 'Aggiungi un banner per parlare agli ospiti di un\'offerta, di un nuovo piatto o degli orari di apertura.', 'banner_saved' => 'Banner salvato.', 'banner_deleted' => 'Banner eliminato.',
    'banner_title' => 'Titolo', 'banner_text' => 'Testo breve', 'banner_link' => 'Link', 'banner_link_hint' => 'Un indirizzo https:// oppure #cat-12 per saltare a una sezione del menu. Facoltativo.', 'banner_button' => 'Testo del pulsante',
    'banner_starts' => 'Mostra dal', 'banner_ends' => 'Mostra fino al', 'banner_popup' => 'Mostra come pop-up (una volta per visita) invece che come banner', 'popup' => 'Pop-up', 'status_off' => 'Disattivo',

    'review_url' => 'Link alla recensione pubblica', 'review_url_hint' => 'Ad esempio il link alle tue recensioni su Google. Gli ospiti soddisfatti vengono invitati a condividere lì la loro valutazione.', 'review_min' => 'Proponilo da (stelle)', 'review_public_invite' => 'Siamo contenti che ti sia piaciuto! Lo condivideresti anche su Google?', 'review_public_button' => 'Scrivi una recensione pubblica',

    'ai_assistant' => 'Assistente del menu nel menu degli ospiti (IA)', 'ai_assistant_hint' => 'Gli ospiti possono fare domande su piatti, diete e allergeni. Risponde solo in base al tuo menu e ogni risposta usa un credito IA.',

    // Growth: channels, segments, happy hour, gift cards, NPS, pixels, link page, flyer, widget.
    'campaign_channel' => 'Canale', 'channel_email' => 'Email', 'channel_sms' => 'SMS', 'channel_whatsapp' => 'WhatsApp', 'channel_push' => 'Notifica push',
    'channel_push_help' => 'Inviata ai browser i cui ospiti hanno toccato "Ricevi offerte" sul tuo menu. Il nome della campagna è il titolo, il testo è il messaggio (massimo 180 caratteri). I segmenti non si applicano.', 'push_offer' => 'Ricevi offerte', 'push_on' => 'Riceverai le nostre offerte.', 'push_denied' => 'Le notifiche sono bloccate o non supportate in questo browser.',
    'channel_help' => 'Inviata agli ospiti che hanno acconsentito al marketing e lasciato un numero di telefono. Richiede un provider SMS/WhatsApp nelle Impostazioni e usa il limite mensile di messaggi. Massimo 600 caratteri; usa {{name}} per il nome dell\'ospite.',
    'campaign_segment' => 'Pubblico', 'segment_all' => 'Tutti', 'segment_new' => 'Nuovi ospiti (0-1 ordini)', 'segment_regulars' => 'Habitué (livello argento e oltre)', 'segment_vip' => 'VIP (livello oro)', 'segment_lapsed' => 'Ospiti inattivi',
    'sms_stop' => 'Per non ricevere messaggi: :url',
    'growth_section' => 'Livelli, sondaggi e pilota automatico', 'tier_silver' => 'Livello argento da (ordini)', 'tier_gold' => 'Livello oro da (ordini)', 'tier_help' => 'I livelli raggruppano gli ospiti per le campagne. Si calcolano dagli ordini completati.',
    'nps_enabled' => 'Chiedi la domanda da 0 a 10 "ci consiglieresti" insieme alla valutazione',
    'autopilot_winback' => 'Pilota automatico: riconquista gli ospiti inattivi', 'autopilot_help' => 'Una volta al giorno, gli ospiti che hanno acconsentito al marketing e non ordinano da un po\' ricevono via email un codice personale monouso (al massimo una volta ogni 90 giorni).',
    'autopilot_days' => 'Assenti da (giorni)', 'autopilot_percent' => 'Sconto (%)',
    'pixels_section' => 'Tracciamento pubblicitario', 'pixels_help' => 'Incolla solo l\'ID. Gli script si caricano nel menu degli ospiti e vengono saltati se l\'ospite invia Do Not Track. Menzionali nella tua informativa sulla privacy.',
    'pixel_meta' => 'ID pixel Meta (Facebook)', 'pixel_ga' => 'ID tag Google', 'pixel_tiktok' => 'ID pixel TikTok',
    'links_section' => 'Pagina link, volantino e pulsante per il sito', 'links_help' => 'La tua pagina link-in-bio per i profili social: :url',
    'link_phone' => 'Telefono', 'link_whatsapp' => 'Numero WhatsApp', 'link_whatsapp_hint' => 'Cifre con prefisso del paese, es. 393331112233', 'link_instagram' => 'URL di Instagram', 'link_facebook' => 'URL di Facebook', 'link_website' => 'URL del sito web',
    'link_label_menu' => 'Vedi il menu e ordina', 'link_label_whatsapp' => 'Scrivici su WhatsApp', 'link_label_phone' => 'Chiamaci', 'link_label_instagram' => 'Instagram', 'link_label_facebook' => 'Facebook', 'link_label_website' => 'Il nostro sito web', 'link_label_review' => 'Lascia una recensione',
    'flyer_open' => 'Volantino stampabile', 'flyer_print' => 'Stampa', 'flyer_scan' => 'Scansiona per vedere il nostro menu e ordinare', 'widget_open' => 'Pulsante per il sito', 'widget_button' => 'Ordina online',
    'widget_title' => 'Pulsante per il sito', 'widget_sub' => 'Permetti ai visitatori del tuo sito di aprire il menu senza lasciare la pagina.', 'widget_button_title' => 'Pulsante fluttuante "Ordina online"', 'widget_button_help' => 'Incollalo prima del tag di chiusura body del tuo sito.',
    'widget_iframe_title' => 'Incorpora il menu in una pagina', 'widget_iframe_help' => 'Oppure mostra tutto il menu dentro una pagina.',
    'nps_question' => 'Quanto è probabile che ci consiglieresti a un amico?', 'nps_low' => 'Poco probabile', 'nps_high' => 'Molto probabile',
    'nps_score' => 'Net Promoter Score', 'nps_breakdown' => ':p promotori, :n passivi, :d detrattori',
    'templates' => 'Parti da un modello', 'template_first_order' => 'Benvenuto 10%', 'template_weekend' => 'Weekend 15%', 'template_big_basket' => 'Bonus grande ordine', 'template_flash' => 'Vendita flash 20%',
    'pricing_title' => 'Happy hour', 'pricing_sub' => 'Sconti automatici per certi giorni e orari. Vengono applicati dal server quando si calcola il prezzo del carrello; vince la regola migliore e le regole non si sommano mai.',
    'rule_name' => 'Nome', 'rule_name_ph' => 'Bevande in happy hour', 'rule_percent' => 'Sconto in percentuale', 'rule_from' => 'Dalle', 'rule_to' => 'Alle', 'rule_scope' => 'Si applica a', 'rule_all_menu' => 'Tutto il menu',
    'rule_days' => 'Giorni', 'rule_days_help' => 'Lascia tutto deselezionato per ogni giorno.', 'rule_add' => 'Aggiungi regola', 'rule_saved' => 'Regola salvata.', 'rule_deleted' => 'Regola eliminata.', 'every_day' => 'Ogni giorno',
    'rules_empty' => 'Ancora nessun happy hour', 'rules_empty_text' => 'Aggiungi una regola come il 20% di sconto sulle bevande nei giorni feriali dalle 16:00 alle 18:00.', 'delete_rule_confirm' => 'Eliminare questa regola?',
    'day_0' => 'Dom', 'day_1' => 'Lun', 'day_2' => 'Mar', 'day_3' => 'Mer', 'day_4' => 'Gio', 'day_5' => 'Ven', 'day_6' => 'Sab',
    'gifts_title' => 'Carte regalo', 'gifts_sub' => 'Vendi o regala carte regalo. Gli ospiti digitano il codice al pagamento e il saldo si consuma.',
    'gift_amount' => 'Importo', 'gift_valid_days' => 'Valida per (giorni)', 'gift_valid_help' => 'Vuoto = non scade mai.', 'gift_email' => 'Invia a (email)', 'gift_email_help' => 'Facoltativo. Il destinatario riceve il codice via email.', 'gift_note' => 'Messaggio',
    'gift_issue' => 'Emetti carta regalo', 'gift_issued' => 'Carta regalo :code creata.', 'gifts_empty' => 'Ancora nessuna carta regalo', 'gifts_empty_text' => 'Emettine una qui sopra e consegna il codice a chi l\'ha acquistata.',
    'gift_balance' => 'Saldo :balance di :initial', 'gift_expires' => 'scade il :date',
    'weekly_digest' => 'Inviami via email un riepilogo della settimana scorsa ogni lunedì',
    'privacy_section' => 'Privacy', 'retention_months' => 'Cancella i dati personali degli ospiti dopo (mesi)',
    'retention_help' => '0 conserva tutto. Altrimenti, gli ospiti che non ordinano da tutto questo tempo vengono eliminati e il loro nome, telefono, email e indirizzo vengono rimossi dai vecchi ordini. Gli ordini stessi (importi, piatti) restano per la tua contabilità.',
    'segment_birthday' => 'Compleanno questo mese', 'autopilot_birthday' => 'Pilota automatico: regalo di compleanno', 'birthday_percent' => 'Sconto di compleanno (%)',
    'segments_title' => 'Segmenti', 'segments_sub' => 'Salva un pubblico una volta e sceglilo in qualsiasi campagna. Un ospite deve rispettare ogni regola che compili.',
    'segment_name' => 'Nome', 'segment_name_ph' => 'Habitué diventati silenziosi', 'segment_rules_help' => 'Lascia vuota una regola per ignorarla. Vengono contattati solo gli ospiti che hanno acconsentito al marketing.', 'segment_save' => 'Salva segmento',
    'rule_min_orders' => 'Almeno questo numero di ordini', 'rule_max_orders' => 'Al massimo questo numero di ordini', 'rule_min_spent' => 'Speso almeno', 'rule_active_within' => 'Ha ordinato negli ultimi (giorni)', 'rule_inactive_for' => 'Non ordina da (giorni)', 'rule_joined_within' => 'Nuovo negli ultimi (giorni)',
    'rule_birthday' => 'Compleanno', 'rule_birthday_this' => 'Questo mese', 'rule_birthday_next' => 'Il mese prossimo', 'rule_tier' => 'Livello',
    'rule_value_this_month' => 'questo mese', 'rule_value_next_month' => 'il mese prossimo', 'rule_value_bronze' => 'bronzo', 'rule_value_silver' => 'argento', 'rule_value_gold' => 'oro',
    'rule_label_min_orders' => ':value+ ordini', 'rule_label_max_orders' => 'fino a :value ordini', 'rule_label_min_spent' => 'speso :value+', 'rule_label_active_within_days' => 'ha ordinato negli ultimi :value giorni', 'rule_label_inactive_for_days' => 'inattivo da :value+ giorni',
    'rule_label_joined_within_days' => 'nuovo negli ultimi :value giorni', 'rule_label_birthday' => 'compleanno :value', 'rule_label_tier' => 'livello :value',
    'segment_everyone' => 'Tutti', 'segment_reach' => '{0} nessuno è raggiungibile|{1} :count ospite è raggiungibile|[2,*] :count ospiti sono raggiungibili', 'segment_saved' => 'Segmento salvato.', 'segment_deleted' => 'Segmento eliminato.', 'segment_delete_confirm' => 'Eliminare questo segmento?', 'segments_empty' => 'Nessun segmento salvato', 'segments_empty_text' => 'Creane uno qui sopra, poi sceglilo quando scrivi una campagna.',
];
