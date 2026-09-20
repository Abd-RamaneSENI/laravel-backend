<?php

return [

    'paths' => ['api/*', 'webhooks/*', 'livres/*', 'telechargements/*', 'lecture/*', 'commandes/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://react-frontend-pi-nine.vercel.app',
        'http://localhost:5173', 
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
