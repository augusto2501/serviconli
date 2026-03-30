<?php

// Parámetros transversales Serviconli (RF-111 / migración futura)

return [

    /*
    |--------------------------------------------------------------------------
    | Credenciales de portales (operador PILA, EPS, AFP, ARL, CCF)
    |--------------------------------------------------------------------------
    |
    | Por defecto se almacenan en texto plano en BD. Cuando pase a producción
    | con cifrado (p. ej. AES-256-CBC / cast encrypted de Laravel), poner
    | PORTAL_CREDENTIALS_ENCRYPT=true y ejecutar migración de datos si aplica.
    |
    */
    'portal_credentials' => [
        'encrypt' => env('PORTAL_CREDENTIALS_ENCRYPT', false),
    ],

];
