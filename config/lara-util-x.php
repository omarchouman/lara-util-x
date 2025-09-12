<?php

return [
    'llm' => [
        'default_provider' => env('LLM_DEFAULT_PROVIDER', 'openai'), // openai | gemini
    ],
    
    'cache' => [
        'default_expiration' => 60,
        'default_tags' => [],
    ],

    'rate_limiting' => [
        'default_max_attempts' => 60,
        'default_decay_minutes' => 1,
        'cache_prefix' => 'rate_limit:',
        'defaults' => [
            'api' => [
                'max_attempts' => 60,
                'decay_minutes' => 1,
            ],
            'auth' => [
                'max_attempts' => 5,
                'decay_minutes' => 15,
            ],
            'download' => [
                'max_attempts' => 3,
                'decay_minutes' => 1,
            ],
        ],
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
    
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'max_retries' => env('GEMINI_MAX_RETRIES', 3),
        'retry_delay' => env('GEMINI_RETRY_DELAY', 2),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-2.0-flash'),
        'default_temperature' => env('GEMINI_DEFAULT_TEMPERATURE', 0.7),
        'default_max_tokens' => env('GEMINI_DEFAULT_MAX_TOKENS', 300),
        'default_top_p' => env('GEMINI_DEFAULT_TOP_P', 1.0),
    ],
];