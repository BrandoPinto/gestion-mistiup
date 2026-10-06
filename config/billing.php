<?php

/*
| Parámetros de facturación. Pasarán a Configuración (base de datos) en la Fase 11;
| mientras tanto se leen de aquí / .env.
*/

return [

    // Días de anticipación con los que se genera el cobro de un ciclo antes de su vencimiento.
    'charge_lead_days' => (int) env('BILLING_CHARGE_LEAD_DAYS', 45),

    // Tope de cobros que se generan de una vez para un contrato (evita creaciones masivas por error).
    'max_charges_per_run' => 60,

    // Comprobantes de pago.
    'attachments' => [
        'disk' => 'local', // storage/app/private: nunca accesible por URL directa.
        'max_kb' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    ],

];
