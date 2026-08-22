<?php

return [
    'products' => 'Produkter', 'services' => 'Tilleggstjenester', 'quantity' => 'Antall', 'unit_price' => 'Pris per enhet',
    'line_total' => 'Linjesum', 'product_total' => 'Produktsum', 'service_total' => 'Tjenestesum', 'order_total' => 'Ordretotal',
    'no_services' => 'Ingen tilleggstjenester er bestilt.',
    'immutable_snapshot' => 'Uforanderlig varelinje- og prisøyeblikksbilde.',
    'ndi_unavailable' => 'NDI-reservering og kansellering er ikke tilgjengelig her.',
    'ndi_reference' => 'Ekstern bestillingsreferanse', 'carrier' => 'Transportør', 'status_checked' => 'Status sist kontrollert',
    'despatch_checked' => 'Forsendelse sist kontrollert', 'error_code' => 'Feilkode', 'tracking' => 'Sporing', 'not_available' => 'Ikke tilgjengelig',
    'transition' => 'Lokal statusendring', 'transition_help' => 'Dette endrer bare EasyTire sin lokale status. Betaling og NDI blir ikke kalt.',
    'payment_operations' => 'Dintero-betaling',
    'payment_provider' => 'Leverandør', 'payment_session' => 'Betalingsøkt', 'payment_transaction' => 'Transaksjon',
    'goods_ready' => 'Varer mottatt og klare', 'goods_not_ready' => 'Ikke bekreftet ennå',
    'capture_help' => 'Bruk bare etter at varene fysisk er mottatt og klare for montering eller henting. Handlingen belaster kunden.',
    'goods_ready_note' => 'Intern bekreftelse / merknad', 'capture_now' => 'Bekreft klar og belast kunden',
    'capture_completed' => 'Dintero-betalingen er belastet.',
    'capture_pending_reconcile' => 'Belastningen ble startet, men sluttstatusen er ikke sikker. Ikke prøv en ny betaling; bruk avstemming.',
    'reconcile' => 'Hent status fra Dintero', 'reconciled' => 'Betalingsstatusen er avstemt mot Dintero.', 'reconcile_failed' => 'Dintero-status kunne ikke avstemmes nå.',
    'void' => 'Frigi reservasjon', 'void_completed' => 'Betalingsreservasjonen er frigitt.', 'void_failed' => 'Reservasjonen kunne ikke frigis sikkert.',
    'order_workflow_warning' => 'Betaling og NDI håndteres bare gjennom de sikre handlingene som vises for ordren.',
];
