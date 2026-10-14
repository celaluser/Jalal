<?php

return [
    // Customers
    'customers_title' => 'Kunden', 'customers_sub' => 'Alle, die bei einer Bestellung ihre Daten hinterlassen haben. Bauen Sie Treue auf, erkennen Sie Stammgäste und schreiben Sie denen, die zugestimmt haben.',
    'search_customers' => 'Name, E-Mail oder Telefon suchen', 'filter_all' => 'Alle', 'filter_opted' => 'Marketing zugestimmt', 'filter_repeat' => 'Stammgäste (2+ Bestellungen)',
    'sort_recent' => 'Letzte Bestellung', 'sort_orders' => 'Meiste Bestellungen', 'sort_spent' => 'Top-Kunden', 'sort_name' => 'Name',
    'col_customer' => 'Kunde', 'col_orders' => 'Bestellungen', 'col_spent' => 'Ausgegeben', 'col_last' => 'Letzte Bestellung', 'col_marketing' => 'Marketing',
    'stat_all' => 'Kunden', 'stat_opted' => 'Per E-Mail erreichbar', 'stat_repeat' => 'Stammgäste',
    'consent_yes' => 'Zugestimmt', 'consent_no' => 'Nein', 'consent_unsub' => 'Abgemeldet', 'anonymous' => 'Gast', 'export' => 'CSV exportieren',
    'customers_empty' => 'Noch keine Kunden', 'customers_empty_text' => 'Gäste erscheinen hier, sobald sie bei einer Bestellung Namen, Telefon oder E-Mail hinterlassen.',
    'customer_since' => 'Kunde seit :date', 'agreed_on' => 'Marketing zugestimmt am :date', 'unsubscribed_on' => 'Abgemeldet am :date', 'no_consent' => 'Hat Marketing-E-Mails nicht zugestimmt.',
    'notes' => 'Private Notizen', 'notes_help' => 'Nur Ihr Team sieht das. Allergien, Vorlieben, alles, was man sich merken sollte.', 'order_history' => 'Bestellungen', 'no_orders' => 'Keine Bestellungen.',
    'delete_customer' => 'Diesen Kunden löschen', 'delete_help' => 'Entfernt den Datensatz und löscht Name, Telefon, E-Mail und Adresse aus den Bestellungen. Umsatzzahlen bleiben erhalten. Das kann nicht rückgängig gemacht werden.', 'delete_confirm' => 'Diesen Kunden löschen und seine persönlichen Daten entfernen?',
    'customer_deleted' => 'Kunde gelöscht und persönliche Daten entfernt.', 'avg_order' => 'Ø Bestellwert',

    // Promo codes
    'promos_title' => 'Aktionscodes', 'promos_sub' => 'Rabattcodes, die Ihre Gäste an der Kasse eingeben. Nicht zu verwechseln mit Abo-Gutscheinen der Plattform.',
    'new_promo' => 'Neuer Code', 'edit_promo' => 'Code bearbeiten', 'promo_code' => 'Code', 'promo_desc' => 'Beschreibung', 'promo_desc_help' => 'Nur Sie sehen das.',
    'promo_type' => 'Rabattart', 'type_percent' => 'Prozent Rabatt', 'type_fixed' => 'Betrag Rabatt', 'promo_value' => 'Wert', 'promo_min' => 'Mindestbestellwert', 'promo_max_uses' => 'Maximale Einlösungen', 'promo_max_help' => 'Leer lassen für unbegrenzt.',
    'promo_starts' => 'Beginn', 'promo_ends' => 'Ende', 'promo_active' => 'Aktiv', 'promo_uses' => 'Eingelöst', 'promo_status' => 'Status',
    'status_active' => 'Aktiv', 'status_off' => 'Ausgeschaltet', 'status_expired' => 'Abgelaufen', 'status_scheduled' => 'Geplant', 'status_used_up' => 'Aufgebraucht', 'reward_badge' => 'Treueprämie', 'reward_for' => 'Für :name',
    'promo_saved' => 'Aktionscode gespeichert.', 'promo_deleted' => 'Aktionscode gelöscht.', 'promos_empty' => 'Noch keine Aktionscodes', 'promos_empty_text' => 'Erstellen Sie einen Code wie WELCOME10 und teilen Sie ihn in sozialen Medien oder in Ihren Kampagnen.',
    'turn_off' => 'Ausschalten', 'turn_on' => 'Einschalten', 'delete_promo_confirm' => 'Diesen Aktionscode löschen?', 'min_order_label' => 'ab :amount',
    'reward_percent' => ':value% Rabatt', 'reward_fixed' => ':amount Rabatt',

    // Guest checkout
    'promo_field' => 'Aktionscode', 'promo_apply' => 'Einlösen', 'promo_remove' => 'Entfernen', 'promo_applied' => 'Code :code angewendet: :amount Rabatt.', 'promo_discount' => 'Rabatt',
    'opt_in' => 'Senden Sie mir Angebote und Neuigkeiten von :name per E-Mail. Ich kann mich jederzeit abmelden.',

    // Reviews
    'reviews_title' => 'Bewertungen', 'reviews_sub' => 'Was Gäste nach ihrer Mahlzeit gesagt haben.', 'reviews_empty' => 'Noch keine Bewertungen', 'reviews_empty_text' => 'Gäste werden eingeladen, ihre Bestellung zu bewerten, sobald sie abgeschlossen ist.',
    'average' => 'Durchschnittsbewertung', 'review_count' => '{1} 1 Bewertung|[2,*] :count Bewertungen', 'filter_low' => 'Niedrige Bewertungen', 'filter_unanswered' => 'Unbeantwortet',
    'low_alert' => '{1} 1 niedrige Bewertung braucht noch eine Antwort.|[2,*] :count niedrige Bewertungen brauchen noch eine Antwort.', 'low_flag' => 'Braucht Aufmerksamkeit',
    'reply' => 'Ihre Antwort', 'reply_save' => 'Antwort speichern', 'reply_help' => 'Wird dem Gast auf seiner Bestellseite angezeigt.', 'hide' => 'Auf der Karte ausblenden', 'show' => 'Auf der Karte anzeigen', 'hidden' => 'Ausgeblendet', 'order_ref' => 'Bestellung :number',
    'public_note' => 'Auf Ihrer Speisekarte sichtbar', 'stars' => '{1} :count Stern|[2,*] :count von 5 Sternen',

    // Guest review form
    'rate_title' => 'Wie war es?', 'rate_sub' => 'Bewerten Sie Ihre Bestellung. Das dauert zehn Sekunden.', 'rate_comment' => 'Möchten Sie noch etwas ergänzen? (optional)', 'rate_public' => 'Meine Bewertung (mit meinem Vornamen) auf der Speisekarte anzeigen',
    'rate_send' => 'Bewertung senden', 'rate_thanks' => 'Vielen Dank für Ihr Feedback!', 'rate_yours' => 'Ihre Bewertung', 'rate_reply' => 'Antwort des Restaurants',
    'review_error_closed' => 'Für diese Bestellung sind keine Bewertungen möglich.', 'review_error_done' => 'Sie haben diese Bestellung bereits bewertet.', 'review_error_rating' => 'Wählen Sie zwischen 1 und 5 Sternen.',
    'rating_on_menu' => ':average (:count)',

    // Loyalty & settings
    'loyalty_title' => 'Treue & Bewertungen', 'loyalty_sub' => 'Belohnen Sie Stammgäste automatisch und fragen Sie Gäste, wie es war.',
    'loyalty_section' => 'Treueprämie', 'loyalty_enabled' => 'Stammgäste belohnen', 'loyalty_enabled_help' => 'Nach jeder n-ten abgeschlossenen Bestellung erhält der Gast einen einmal nutzbaren Aktionscode, den nur er verwenden kann. Er erscheint auf der Bestellseite und in einer E-Mail.',
    'loyalty_every' => 'Prämie nach jeweils … abgeschlossenen Bestellungen', 'loyalty_type' => 'Prämie', 'loyalty_value' => 'Wert', 'loyalty_percent_hint' => 'Prozent der Bestellung', 'loyalty_fixed_hint' => 'Betrag in Ihrer Währung', 'loyalty_valid' => 'Gültig für (Tage)',
    'reviews_section' => 'Bewertungen', 'reviews_enabled' => 'Gäste bitten, abgeschlossene Bestellungen zu bewerten', 'review_email' => 'Zusätzlich per E-Mail fragen, wenn der Gast eine Adresse hinterlassen hat', 'show_rating' => 'Durchschnittsbewertung auf der öffentlichen Speisekarte anzeigen',
    'messages_section' => 'Textnachrichten', 'calling_code' => 'Landesvorwahl', 'calling_code_help' => 'Wird verwendet, um SMS und WhatsApp an Gäste zu senden, die eine lokale Nummer eingegeben haben, z. B. 49 für Deutschland. Leer lassen, um nur Nummern anzuschreiben, die mit + beginnen.',
    'email_section' => 'E-Mail-Versand', 'daily_cap' => 'Kampagnen-E-Mails pro Tag', 'daily_cap_help' => 'Die Plattform erlaubt höchstens :max pro Restaurant und Tag. So bleibt Ihr E-Mail-Konto in gutem Ruf.',
    'reward_earned' => 'Sie haben eine Prämie verdient!', 'reward_earned_text' => 'Danke, dass Sie immer wieder bestellen. Verwenden Sie diesen Code bei Ihrer nächsten Bestellung:', 'reward_valid_until' => 'Gültig bis :date, einmalig, nur für Sie.',
    'there' => 'da',

    // Campaigns
    'campaigns_title' => 'Kampagnen', 'campaigns_sub' => 'Schreiben Sie per E-Mail an Gäste, die zugestimmt haben, von Ihnen zu hören. Jede Nachricht enthält einen Abmeldelink.',
    'new_campaign' => 'Neue Kampagne', 'edit_campaign' => 'Kampagne bearbeiten', 'campaign_name' => 'Name', 'campaign_name_help' => 'Nur Sie sehen das.', 'campaign_subject' => 'Betreff', 'campaign_body' => 'Nachricht',
    'campaign_body_help' => 'Klartext mit einfacher Formatierung (**fett**, [Link](https://…)). Verwenden Sie {{name}} und {{restaurant}}, um zu personalisieren.', 'campaign_audience' => 'Wer sie erhält', 'min_orders' => 'Nur Gäste mit mindestens … Bestellungen', 'min_orders_help' => '0 bedeutet alle, die zugestimmt haben.',
    'audience_now' => '{0} Noch niemand kann dies erhalten.|{1} 1 Gast erhält dies.|[2,*] :count Gäste erhalten dies.', 'audience_note' => 'Nur Gäste, die an der Kasse das E-Mail-Kästchen angehakt haben, eine Adresse haben und sich nicht abgemeldet haben.',
    'campaign_saved' => 'Kampagne gespeichert.', 'campaign_deleted' => 'Kampagne gelöscht.', 'campaign_sending' => 'Ihre Kampagne ist unterwegs. Das kann einige Minuten dauern.',
    'campaign_error_sent' => 'Diese Kampagne wurde bereits gesendet.', 'campaign_error_empty' => 'Diese Kampagne kann noch niemand erhalten.',
    'status_draft' => 'Entwurf', 'status_sending' => 'Wird gesendet', 'status_sent' => 'Gesendet', 'campaigns_empty' => 'Noch keine Kampagnen', 'campaigns_empty_text' => 'Erzählen Sie Ihren Stammgästen von einem neuen Gericht oder einem Angebot für ruhige Tage.',
    'sent_count' => 'Gesendet', 'skipped_count' => 'Nicht gesendet', 'recipients' => 'Empfänger', 'send_test' => 'Test an mich senden', 'send_now' => 'Kampagne senden', 'send_confirm' => 'Diese Kampagne jetzt senden? Das kann nicht rückgängig gemacht werden.',
    'test_sent' => 'Test-E-Mail an :email gesendet.', 'today_room' => 'Heute können noch :count E-Mails versendet werden (Limit :cap).', 'cap_note' => 'Gäste über dem heutigen Limit werden übersprungen. Senden Sie morgen mit einer neuen Kampagne erneut.', 'sent_on' => 'Gesendet am :date',
    'preview' => 'Vorschau', 'unsub_footer' => 'Sie erhalten dies, weil Sie zugestimmt haben, Angebote von :name zu erhalten.', 'unsub_link' => 'Abmelden',

    // Unsubscribe page
    'unsub_title' => 'Abmelden', 'unsub_ask' => 'E-Mails von :name beenden?', 'unsub_ask_text' => 'Sie erhalten keine Angebote und Neuigkeiten mehr. Bestellupdates erreichen Sie weiterhin.', 'unsub_button' => 'Ja, abmelden',
    'unsub_done' => 'Sie sind abgemeldet', 'unsub_done_text' => 'Sie erhalten keine Marketing-E-Mails mehr von :name.', 'unsub_already' => 'Sie sind bereits von :name abgemeldet.',

    // Banners and pop-ups
    'banners_title' => 'Banner & Pop-ups', 'banners_sub' => 'Zeigen Sie ein neues Gericht, ein Angebot oder einen Hinweis oben auf Ihrer Speisekarte.', 'new_banner' => 'Neues Banner', 'edit_banner' => 'Banner bearbeiten',
    'banners_empty' => 'Noch keine Banner', 'banners_empty_text' => 'Fügen Sie ein Banner hinzu, um Gäste auf ein Angebot, ein neues Gericht oder Öffnungszeiten hinzuweisen.', 'banner_saved' => 'Banner gespeichert.', 'banner_deleted' => 'Banner gelöscht.',
    'banner_title' => 'Überschrift', 'banner_text' => 'Kurztext', 'banner_link' => 'Link', 'banner_link_hint' => 'Eine https://-Adresse oder #cat-12, um zu einem Bereich der Speisekarte zu springen. Optional.', 'banner_button' => 'Schaltflächentext',
    'banner_starts' => 'Anzeigen ab', 'banner_ends' => 'Anzeigen bis', 'banner_popup' => 'Als Pop-up anzeigen (einmal pro Besuch) statt als Banner', 'popup' => 'Pop-up', 'status_off' => 'Aus',

    'review_url' => 'Link zur öffentlichen Bewertung', 'review_url_hint' => 'Zum Beispiel Ihr Google-Bewertungslink. Zufriedene Gäste werden eingeladen, ihre Bewertung dort zu teilen.', 'review_min' => 'Anbieten ab (Sterne)', 'review_public_invite' => 'Schön, dass es Ihnen gefallen hat! Möchten Sie das auch bei Google teilen?', 'review_public_button' => 'Öffentliche Bewertung schreiben',

    'ai_assistant' => 'Speisekarten-Assistent in der Gästekarte (KI)', 'ai_assistant_hint' => 'Gäste können nach Gerichten, Ernährungsweisen und Allergenen fragen. Er antwortet nur anhand Ihrer Speisekarte, und jede Antwort verbraucht einen KI-Credit.',

    // Growth: channels, segments, happy hour, gift cards, NPS, pixels, link page, flyer, widget.
    'campaign_channel' => 'Kanal', 'channel_email' => 'E-Mail', 'channel_sms' => 'SMS', 'channel_whatsapp' => 'WhatsApp', 'channel_push' => 'Push-Benachrichtigung',
    'channel_push_help' => 'Wird an Browser gesendet, deren Gäste auf Ihrer Speisekarte "Angebote erhalten" angetippt haben. Der Kampagnenname ist der Titel, der Text die Nachricht (höchstens 180 Zeichen). Segmente gelten hier nicht.', 'push_offer' => 'Angebote erhalten', 'push_on' => 'Sie erhalten unsere Angebote.', 'push_denied' => 'Benachrichtigungen sind blockiert oder in diesem Browser nicht unterstützt.',
    'channel_help' => 'Wird an Gäste gesendet, die dem Marketing zugestimmt und eine Telefonnummer hinterlassen haben. Erfordert einen SMS-/WhatsApp-Anbieter in den Einstellungen und verbraucht Ihr monatliches Nachrichtenkontingent. Höchstens 600 Zeichen; verwenden Sie {{name}} für den Namen des Gastes.',
    'campaign_segment' => 'Zielgruppe', 'segment_all' => 'Alle', 'segment_new' => 'Neue Gäste (0-1 Bestellungen)', 'segment_regulars' => 'Stammgäste (Silber und höher)', 'segment_vip' => 'VIP (Gold)', 'segment_lapsed' => 'Inaktive Gäste',
    'sms_stop' => 'Nachrichten beenden: :url',
    'growth_section' => 'Stufen, Umfragen und Autopilot', 'tier_silver' => 'Stufe Silber ab (Bestellungen)', 'tier_gold' => 'Stufe Gold ab (Bestellungen)', 'tier_help' => 'Stufen gruppieren Gäste für Kampagnen. Sie werden aus abgeschlossenen Bestellungen berechnet.',
    'nps_enabled' => 'Die 0-10-Frage "Würden Sie uns weiterempfehlen" zusammen mit der Bewertung stellen',
    'autopilot_winback' => 'Autopilot: inaktive Gäste zurückgewinnen', 'autopilot_help' => 'Einmal täglich erhalten Gäste, die dem Marketing zugestimmt haben und länger nicht da waren, per E-Mail einen persönlichen Einmalcode (höchstens alle 90 Tage).',
    'autopilot_days' => 'Abwesend seit (Tagen)', 'autopilot_percent' => 'Rabatt (%)',
    'pixels_section' => 'Werbe-Tracking', 'pixels_help' => 'Fügen Sie nur die ID ein. Die Skripte werden in der Gästespeisekarte geladen und übersprungen, wenn der Gast Do Not Track sendet. Erwähnen Sie sie in Ihrer Datenschutzerklärung.',
    'pixel_meta' => 'Meta-(Facebook-)Pixel-ID', 'pixel_ga' => 'Google-Tag-ID', 'pixel_tiktok' => 'TikTok-Pixel-ID',
    'links_section' => 'Linkseite, Flyer und Website-Button', 'links_help' => 'Ihre Link-in-Bio-Seite für Social-Media-Profile: :url',
    'link_phone' => 'Telefon', 'link_whatsapp' => 'WhatsApp-Nummer', 'link_whatsapp_hint' => 'Ziffern mit Landesvorwahl, z. B. 4915112345678', 'link_instagram' => 'Instagram-URL', 'link_facebook' => 'Facebook-URL', 'link_website' => 'Website-URL',
    'link_label_menu' => 'Speisekarte ansehen & bestellen', 'link_label_whatsapp' => 'Per WhatsApp chatten', 'link_label_phone' => 'Anrufen', 'link_label_instagram' => 'Instagram', 'link_label_facebook' => 'Facebook', 'link_label_website' => 'Unsere Website', 'link_label_review' => 'Bewertung abgeben',
    'flyer_open' => 'Druckbarer Flyer', 'flyer_print' => 'Drucken', 'flyer_scan' => 'Scannen und Speisekarte ansehen und bestellen', 'widget_open' => 'Website-Button', 'widget_button' => 'Online bestellen',
    'widget_title' => 'Website-Button', 'widget_sub' => 'Lassen Sie Besucher Ihrer eigenen Website die Speisekarte öffnen, ohne die Seite zu verlassen.', 'widget_button_title' => 'Schwebender Button "Online bestellen"', 'widget_button_help' => 'Vor dem schließenden Body-Tag Ihrer Website einfügen.',
    'widget_iframe_title' => 'Speisekarte in eine Seite einbetten', 'widget_iframe_help' => 'Oder zeigen Sie die ganze Speisekarte innerhalb einer Seite.',
    'nps_question' => 'Wie wahrscheinlich ist es, dass Sie uns einem Freund weiterempfehlen?', 'nps_low' => 'Unwahrscheinlich', 'nps_high' => 'Sehr wahrscheinlich',
    'nps_score' => 'Net Promoter Score', 'nps_breakdown' => ':p Promotoren, :n Passive, :d Kritiker',
    'templates' => 'Mit einer Vorlage beginnen', 'template_first_order' => 'Willkommen 10%', 'template_weekend' => 'Wochenende 15%', 'template_big_basket' => 'Bonus für große Bestellungen', 'template_flash' => 'Blitzangebot 20%',
    'pricing_title' => 'Happy Hour', 'pricing_sub' => 'Automatische Rabatte für bestimmte Tage und Zeiten. Sie werden vom Server bei der Preisberechnung des Warenkorbs angewendet; die beste Regel gewinnt, Regeln werden nie kombiniert.',
    'rule_name' => 'Name', 'rule_name_ph' => 'Happy Hour Getränke', 'rule_percent' => 'Prozent Rabatt', 'rule_from' => 'Von', 'rule_to' => 'Bis', 'rule_scope' => 'Gilt für', 'rule_all_menu' => 'Ganze Speisekarte',
    'rule_days' => 'Tage', 'rule_days_help' => 'Alle abgewählt lassen für jeden Tag.', 'rule_add' => 'Regel hinzufügen', 'rule_saved' => 'Regel gespeichert.', 'rule_deleted' => 'Regel gelöscht.', 'every_day' => 'Jeden Tag',
    'rules_empty' => 'Noch keine Happy Hour', 'rules_empty_text' => 'Fügen Sie eine Regel hinzu, z. B. 20% Rabatt auf Getränke werktags von 16:00 bis 18:00 Uhr.', 'delete_rule_confirm' => 'Diese Regel löschen?',
    'day_0' => 'So', 'day_1' => 'Mo', 'day_2' => 'Di', 'day_3' => 'Mi', 'day_4' => 'Do', 'day_5' => 'Fr', 'day_6' => 'Sa',
    'gifts_title' => 'Geschenkkarten', 'gifts_sub' => 'Verkaufen oder verschenken Sie Geschenkkarten. Gäste geben den Code an der Kasse ein, und das Guthaben wird aufgebraucht.',
    'gift_amount' => 'Betrag', 'gift_valid_days' => 'Gültig für (Tage)', 'gift_valid_help' => 'Leer = läuft nie ab.', 'gift_email' => 'Senden an (E-Mail)', 'gift_email_help' => 'Optional. Der Empfänger erhält den Code per E-Mail.', 'gift_note' => 'Nachricht',
    'gift_issue' => 'Geschenkkarte ausstellen', 'gift_issued' => 'Geschenkkarte :code erstellt.', 'gifts_empty' => 'Noch keine Geschenkkarten', 'gifts_empty_text' => 'Stellen Sie oben eine aus und übergeben Sie den Code dem Käufer.',
    'gift_balance' => 'Guthaben :balance von :initial', 'gift_expires' => 'läuft ab am :date',
    'weekly_digest' => 'Senden Sie mir jeden Montag eine Zusammenfassung der letzten Woche per E-Mail',
    'privacy_section' => 'Datenschutz', 'retention_months' => 'Personenbezogene Gästedaten löschen nach (Monaten)',
    'retention_help' => '0 behält alles. Andernfalls werden Gäste, die so lange nicht bestellt haben, gelöscht, und ihr Name, Telefon, E-Mail und Adresse werden aus alten Bestellungen entfernt. Die Bestellungen selbst (Beträge, Gerichte) bleiben für Ihre Buchhaltung erhalten.',
    'segment_birthday' => 'Geburtstag in diesem Monat', 'autopilot_birthday' => 'Autopilot: Geburtstagsüberraschung', 'birthday_percent' => 'Geburtstagsrabatt (%)',
    'segments_title' => 'Segmente', 'segments_sub' => 'Speichern Sie eine Zielgruppe einmal und wählen Sie sie in jeder Kampagne. Ein Gast muss jeder ausgefüllten Regel entsprechen.',
    'segment_name' => 'Name', 'segment_name_ph' => 'Stammgäste, die still wurden', 'segment_rules_help' => 'Lassen Sie eine Regel leer, um sie zu ignorieren. Es werden nur Gäste kontaktiert, die dem Marketing zugestimmt haben.', 'segment_save' => 'Segment speichern',
    'rule_min_orders' => 'Mindestens so viele Bestellungen', 'rule_max_orders' => 'Höchstens so viele Bestellungen', 'rule_min_spent' => 'Mindestens ausgegeben', 'rule_active_within' => 'Bestellt in den letzten (Tagen)', 'rule_inactive_for' => 'Nicht bestellt seit (Tagen)', 'rule_joined_within' => 'Neu in den letzten (Tagen)',
    'rule_birthday' => 'Geburtstag', 'rule_birthday_this' => 'Diesen Monat', 'rule_birthday_next' => 'Nächsten Monat', 'rule_tier' => 'Stufe',
    'rule_value_this_month' => 'diesen Monat', 'rule_value_next_month' => 'nächsten Monat', 'rule_value_bronze' => 'Bronze', 'rule_value_silver' => 'Silber', 'rule_value_gold' => 'Gold',
    'rule_label_min_orders' => ':value+ Bestellungen', 'rule_label_max_orders' => 'bis zu :value Bestellungen', 'rule_label_min_spent' => ':value+ ausgegeben', 'rule_label_active_within_days' => 'bestellt in den letzten :value Tagen', 'rule_label_inactive_for_days' => 'seit :value+ Tagen still',
    'rule_label_joined_within_days' => 'neu in den letzten :value Tagen', 'rule_label_birthday' => 'Geburtstag :value', 'rule_label_tier' => 'Stufe :value',
    'segment_everyone' => 'Alle', 'segment_reach' => '{0} niemand ist erreichbar|{1} :count Gast ist erreichbar|[2,*] :count Gäste sind erreichbar', 'segment_saved' => 'Segment gespeichert.', 'segment_deleted' => 'Segment gelöscht.', 'segment_delete_confirm' => 'Dieses Segment löschen?', 'segments_empty' => 'Keine gespeicherten Segmente', 'segments_empty_text' => 'Erstellen Sie oben eines und wählen Sie es, wenn Sie eine Kampagne schreiben.',
];
