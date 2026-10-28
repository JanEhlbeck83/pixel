<?php

/*
|--------------------------------------------------------------------------
| Rechtliche Angaben (Impressum, Datenschutz, Nutzungsbedingungen)
|--------------------------------------------------------------------------
|
| Betreiberdaten werden in der .env gepflegt (siehe .env.example, Abschnitt
| "RECHTLICHES"). Leere Werte erscheinen auf den Seiten als deutlich
| markierter Platzhalter und MÜSSEN vor dem Livegang ausgefüllt werden.
|
*/

return [
    'name' => env('LEGAL_NAME'),
    'street' => env('LEGAL_STREET'),
    'zip_city' => env('LEGAL_ZIP_CITY'),
    'country' => env('LEGAL_COUNTRY', 'Deutschland'),
    'email' => env('LEGAL_EMAIL'),
    'phone' => env('LEGAL_PHONE'),
    // Nur bei Unternehmen/Gewerbe: Umsatzsteuer-ID nach § 27a UStG
    'vat_id' => env('LEGAL_VAT_ID'),
    // Nur bei Einrichtungen mit Pflicht (z. B. GmbH): Registergericht und -nummer
    'register' => env('LEGAL_REGISTER'),
    'responsible' => env('LEGAL_RESPONSIBLE'),
    'hosting' => env('LEGAL_HOSTING'),
    'updated_at' => '2026-10-28',
];
