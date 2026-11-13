<?php

return [
    'url' => env('CHURN_API_URL', 'https://127.0.0.1:8443'),
    'key_file' => env('CHURN_CLIENT_KEY_FILE'),
    'ca_file' => env('CHURN_CLIENT_CA_FILE'),
    'model_version' => env('CHURN_EXPECTED_MODEL', 'churn180-v1'),
];
