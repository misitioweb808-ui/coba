<?php

return [
    'bot' => [
        'chat_id' => env("TELEGRAM_CHAT_ID"),
        'temas' => [
            'LOGGING' => env("TELEGRAM_LOGGING_GROUP_ID"),
            'MAIN' => env("TELEGRAM_MAIN_GROUP_ID"),
        ]
    ],
];
