<?php

return [
    'title' => 'Add-ons', 'sub' => 'Zusatzfunktionen als signierte Pakete. Sie laufen mit vollem Zugriff auf die Anwendung. Installieren Sie daher nur Add-ons aus vertrauenswürdigen Quellen.',
    'install' => 'Add-on installieren', 'install_help' => 'Laden Sie die erhaltene .zip-Datei hoch. Signatur und alle Dateien werden zuerst geprüft. Neue Add-ons sind zunächst ausgeschaltet.',
    'trust_warning' => 'Ein Add-on kann alles lesen und ändern, auch Ihre Datenbank. Erstellen Sie vorher ein Backup.', 'install_button' => 'Prüfen und installieren',
    'installed_title' => 'Installierte Add-ons', 'installed' => ':name installiert. Schalten Sie es ein, um es zu nutzen.', 'updated' => 'Add-on aktualisiert.', 'removed' => 'Add-on entfernt. Seine Daten bleiben in der Datenbank.',
    'on' => 'An', 'off' => 'Aus', 'turn_on' => 'Einschalten', 'turn_off' => 'Ausschalten', 'remove_confirm' => 'Dieses Add-on entfernen? Seine Dateien werden gelöscht, die Datenbanktabellen bleiben erhalten.',
    'empty' => 'Keine Add-ons installiert', 'empty_text' => 'Gekaufte oder selbst entwickelte Add-ons erscheinen hier.',
    'not_an_addon' => 'Dieses Paket ist kein Add-on (alle Dateien müssen in addons/<name>/ liegen).', 'invalid_manifest' => 'Die Add-on-Beschreibung (addon.json) fehlt oder ist ungültig.',
    'needs_core' => 'Erfordert Anwendungsversion :version oder neuer.', 'provider_missing' => 'Der Add-on-Code konnte nicht geladen werden.', 'not_found' => 'Add-on nicht gefunden.',
];
