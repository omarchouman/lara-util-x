<?php

return [
    'cache' => [
        'default_expiration' => 60,
        'default_tags' => [],
    ],
    
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'max_retries' => env('OPENAI_MAX_RETRIES', 3),
        'retry_delay' => env('OPENAI_RETRY_DELAY', 2),
        'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-3.5-turbo'),
        'default_temperature' => env('OPENAI_DEFAULT_TEMPERATURE', 0.7),
        'default_max_tokens' => env('OPENAI_DEFAULT_MAX_TOKENS', 300),
        'default_top_p' => env('OPENAI_DEFAULT_TOP_P', 1.0),
    ],
];