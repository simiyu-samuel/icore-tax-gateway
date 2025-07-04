<?php

return [
    /*
    |--------------------------------------------------------------------------
    | KRA API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Kenya Revenue Authority (KRA) API endpoints
    | and connection settings.
    |
    */

    // Base URLs for different environments
    'sandbox_base_url' => env('KRA_API_SANDBOX_BASE_URL', 'https://sandbox.kra.go.ke/api'),
    'production_base_url' => env('KRA_API_PRODUCTION_BASE_URL', 'https://api.kra.go.ke/api'),

    // Timeout settings (in milliseconds)
    'strict_timeout_ms' => env('KRA_STRICT_TIMEOUT_MS', 1000), // For OSCU/VSCU calls
    'default_timeout_ms' => env('KRA_DEFAULT_TIMEOUT_MS', 60000), // For general API calls

    // Retry settings
    'max_retries' => env('KRA_MAX_RETRIES', 3),
    'retry_delay_ms' => env('KRA_RETRY_DELAY_MS', 100),

    // API Key header name
    'api_key_header' => env('KRA_API_KEY_HEADER', 'X-API-Key'),

    // Logging settings
    'log_requests' => env('KRA_LOG_REQUESTS', true),
    'log_responses' => env('KRA_LOG_RESPONSES', true),
]; 