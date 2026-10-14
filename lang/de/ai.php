<?php

return [
    'error_not_configured' => 'KI ist noch nicht eingerichtet. Bitten Sie den Plattform-Administrator, einen KI-Schlüssel hinzuzufügen.',
    'error_no_credits' => 'Sie haben alle KI-Credits Ihres Tarifs für diesen Monat verbraucht. Sie werden am 1. erneuert, oder Sie wechseln in einen höheren Tarif.',
    'error_provider_error' => 'Der KI-Dienst hat nicht geantwortet. Bitte versuchen Sie es gleich noch einmal.',
    'error_rate_limited' => 'Der KI-Dienst ist gerade ausgelastet. Bitte versuchen Sie es in einer Minute erneut.',
    'error_bad_response' => 'Die KI konnte diesmal keine brauchbare Antwort erzeugen. Bitte versuchen Sie es erneut oder geben Sie mehr Details an.',
    'error_limit_categories' => 'Der Import dieser Speisekarte würde das Kategorienlimit Ihres Tarifs überschreiten.',
    'error_limit_products' => 'Der Import dieser Speisekarte würde das Produktlimit Ihres Tarifs überschreiten.',

    'write' => 'Mit KI schreiben', 'writing' => 'Schreibe…', 'hints' => 'Zutaten oder Notizen (optional)', 'tone' => 'Stil',
    'tone_appetizing' => 'Appetitlich', 'tone_short' => 'Kurz und schlicht', 'tone_elegant' => 'Elegant', 'tone_playful' => 'Verspielt',
    'translate' => 'Mit KI übersetzen', 'translating' => 'Übersetze…', 'translate_hint' => 'Füllt die leeren Felder der anderen Sprachen aus Ihrer Hauptsprache.',
    'suggest_tags' => 'Allergene mit KI vorschlagen', 'suggesting' => 'Prüfe…',
    'tags_notice' => 'Von der KI vorgeschlagen. Bitte prüfen Sie jedes Allergen selbst: Gäste verlassen sich auf diese Angaben.', 'tags_none' => 'Es wurden keine Allergene oder Ernährungskennzeichen vorgeschlagen.',
    'tags_applied' => 'Angehakt: :list',
    'credits_left' => 'Noch :count Credits in diesem Monat', 'credits_unlimited' => 'KI-Credits sind in Ihrem Tarif unbegrenzt', 'credits_cost' => 'Kostet :count Credit(s)',
    'review_note' => 'Prüfen Sie den Text vor dem Speichern. KI kann Fehler machen.',

    'import_title' => 'Speisekarte mit KI importieren', 'import_sub' => 'Fügen Sie Ihre Speisekarte aus einem PDF, einer Website oder einem Dokument ein. Die KI ordnet sie in Kategorien und Gerichte, und Sie prüfen alles, bevor es hinzugefügt wird.',
    'import_paste' => 'Text Ihrer Speisekarte einfügen', 'import_placeholder' => "VORSPEISEN\nBruschetta - 7.50\nSuppe des Tages - 6\n\nHAUPTGERICHTE\nPizza Margherita, Tomate und Basilikum - 12", 'import_chars' => ':count von :max Zeichen',
    'import_read' => 'Speisekarte einlesen', 'import_cost' => 'Kostet :count Credits', 'import_review' => 'Prüfen', 'import_found' => ':count Gerichte gefunden',
    'import_no_price' => 'Kein Preis gefunden: Diese Gerichte werden ausgeblendet hinzugefügt, bis Sie einen Preis festlegen.', 'import_add' => 'Zu meiner Speisekarte hinzufügen', 'import_adding' => 'Füge hinzu…',
    'import_done' => ':categories Kategorien und :products Gerichte hinzugefügt. :hidden sind ausgeblendet, bis sie einen Preis haben.', 'import_remove' => 'Entfernen', 'import_again' => 'Neu beginnen',
    'import_not_configured_title' => 'KI ist noch nicht verfügbar', 'import_how' => 'So funktioniert es',
    'import_step1' => 'Fügen Sie den Text Ihrer Speisekarte ein.', 'import_step2' => 'Die KI liest ihn und gruppiert ihn. Passen Sie Namen und Preise bei Bedarf an.', 'import_step3' => 'Übernehmen Sie ihn mit einem Klick in Ihre Speisekarte.',
    'category' => 'Kategorie', 'dish' => 'Gerichtname', 'price' => 'Preis', 'description' => 'Beschreibung',
    'button_import' => 'Mit KI importieren',
    'need_name' => 'Geben Sie zuerst den Namen ein.',

    'import_photo' => 'Oder ein Foto der Speisekarte', 'import_photo_cost' => 'Ein Foto kostet :count KI-Credits (jede Seite bei bis zu 5 wird gezählt). Funktioniert mit gedruckten Speisekarten und Screenshots.', 'import_pdf' => 'Oder eine PDF-Speisekarte', 'import_pdf_hint' => 'Nur PDFs mit echtem Text. Bei einem gescannten PDF fotografieren Sie stattdessen die Seite.',
    'error_bad_file' => 'Diese Datei konnte nicht gelesen werden. Versuchen Sie ein gut lesbares JPG, PNG oder PDF.', 'error_pdf_no_text' => 'Dieses PDF enthält keinen lesbaren Text (wahrscheinlich ein Scan). Nutzen Sie stattdessen den Foto-Import.',
    'bulk_title' => 'Gesamte Speisekarte übersetzen', 'bulk_text' => 'Ergänzt im Hintergrund alle fehlenden Übersetzungen für Gerichte und Kategorien. Gesperrte Sprachen bleiben unberührt. Jeder Eintrag verbraucht Übersetzungs-Credits.', 'bulk_start' => 'Alles Fehlende übersetzen',
    'bulk_running' => 'Übersetze…', 'bulk_done' => 'Fertig: :n Texte übersetzt.', 'bulk_stopped' => 'Vorzeitig gestoppt (keine Credits mehr oder KI-Dienst nicht verfügbar). Bereits Erledigtes bleibt erhalten.',
    'draft_reply' => 'Antwort mit KI entwerfen', 'draft_note' => 'Lesen und bearbeiten Sie den Entwurf, bevor Sie ihn senden.', 'insights_title' => 'Ideen aus Ihren Zahlen', 'insights_button' => 'Ideen erhalten', 'insights_note' => 'Von einer KI anhand der obigen Zahlen verfasst. Verstehen Sie sie als Vorschläge.',
    'assistant_name' => 'Speisekarten-Assistent', 'assistant_ask' => 'Fragen zur Speisekarte', 'assistant_placeholder' => 'Gibt es etwas Vegetarisches? Was passt zum Burger?', 'assistant_send' => 'Senden', 'assistant_hint' => 'Die Antworten stammen nur aus dieser Speisekarte. Lassen Sie Allergien vom Personal bestätigen.', 'assistant_error' => 'Der Assistent ist gerade nicht verfügbar.',
];
