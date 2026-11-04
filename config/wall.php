<?php

return [
    // Basisverzeichnis für images/wall-tiles und images/snapshots (Tests überschreiben es).
    'output_path' => public_path(),

    // Kommagetrennte E-Mail-Adressen der Moderatoren (ADMIN_EMAILS in der .env).
    'admin_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_EMAILS', ''))))),
];
