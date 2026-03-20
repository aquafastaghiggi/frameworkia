<?php

declare(strict_types=1);

return [
    'provider' => 'openai',
    'openai' => [
        'api_key' => 'key',
        'model' => 'gpt-4o-mini',
        'base_url' => 'https://api.openai.com/v1/responses',
        'temperature' => 0.2,
        'max_output_tokens' => 1200,
    ],
];