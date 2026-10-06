<?php

/*
 * Rutas que se publican al navegador (Ziggy). Sin sesión solo se exponen las del grupo "guest":
 * el login y el enlace público de cotizaciones no revelan la estructura del panel administrativo.
 */
return [
    'groups' => [
        'guest' => ['login', 'login.store', 'quotes.public', 'quotes.public.pdf', 'branding.logo'],
    ],
];
