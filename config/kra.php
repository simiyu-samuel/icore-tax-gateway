<?php
// config/kra.php

return [
    'api_sandbox_base_url' => env('KRA_API_SANDBOX_BASE_URL', 'https://etims-sbx.kra.go.ke'),
    'api_production_base_url' => env('KRA_API_PRODUCTION_BASE_URL', 'https://etims.kra.go.ke'),
    'vscu_jar_base_url' => env('KRA_VSCU_JAR_BASE_URL', 'http://127.0.0.1:8088'), // Default for local VSCU
    'qr_code_base_url' => env('KRA_QR_CODE_BASE_URL', 'https://etims.kra.go.ke/receipt_validation'), // Hypothetical
    'simulation_mode' => env('KRA_SIMULATION_MODE', false),
    'simulation_responses' => env('KRA_SIMULATION_RESPONSES', true),
];