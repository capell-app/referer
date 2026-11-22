<?php

declare(strict_types=1);

return [
    'navigation' => 'Referrer',
    'title' => 'Referrer',
    'subtitle' => 'Datenschutzfreundliche aggregierte Zählungen erfolgreicher HTML-Seitenaufrufe nach Verweisquelle.',
    'demo' => [
        'seeded' => 'Beispiel-Verweiszahlen wurden für :count Websites hinzugefügt.',
        'production_refused' => 'Beispiel-Verweiszahlen ersetzen echte Besuchszahlen und können daher in der Produktion nicht hinzugefügt werden. Führen Sie dies stattdessen auf einer lokalen oder einer Staging-Kopie aus.',
    ],
    'health' => [
        'storage_label' => 'Referrer: Zähler-Datenbanktabelle vorhanden',
        'storage_passed' => 'Die Referrer-Tabellen für tägliche und Gesamtzählungen sowie für den Aufbewahrungsstatus sind verfügbar.',
        'storage_failed' => 'Der Referrer-Zählerspeicher ist nicht verfügbar.',
        'storage_remediation' => 'Führen Sie die Migrationen des Pakets Referers aus.',
        'middleware_label' => 'Referrer: Erfassung öffentlicher Besuche registriert',
        'middleware_passed' => 'Die Referrer-Middleware ist nach der Grenze des anonymen HTML-Caches registriert.',
        'middleware_failed' => 'Die Referrer-Middleware ist nicht registriert.',
        'middleware_remediation' => 'Leeren Sie den Bootstrap-Cache der Anwendung und prüfen Sie, ob das Paket installiert ist.',
        'write_label' => 'Referrer: Besuchszahlen schreibbar',
        'write_passed' => 'Es wurde kein aktueller Schreibfehler bei den Referrer-Zählern erfasst.',
        'write_failed' => 'Das Schreiben eines Referrer-Zählers ist kürzlich fehlgeschlagen; die Seitenauslieferung bleibt davon unberührt.',
        'write_remediation' => 'Prüfen Sie die Datenbankverbindung und das Anwendungsprotokoll, bevor Sie die Erfassung erneut versuchen.',
    ],
];
