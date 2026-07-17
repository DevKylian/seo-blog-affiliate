<?php

return [

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],

    'semrush' => [
        'key' => env('SEMRUSH_API_KEY'),
        'metrics_url' => env('SEMRUSH_KEYWORD_METRICS_URL', 'https://api.semrush.com/apis/v4/keywords/v1/metrics'),
        'seed_expansion_enabled' => env('SEMRUSH_SEED_EXPANSION_ENABLED', false),
    ],

    'indexnow' => [
        'key' => env('INDEXNOW_KEY'),
    ],

    'google_search_console' => [
        'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL'),
        'service_account_json' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),
    ],

    'bing_webmaster' => [
        'site_url' => env('BING_WEBMASTER_SITE_URL'),
        'api_key' => env('BING_WEBMASTER_API_KEY'),
    ],

    'runtime' => [
        'php_binary' => env('PHP_CLI_BINARY'),
    ],

    'scraping' => [
        'node_binary' => env('NODE_BINARY'),
        'browser_binary' => env('BROWSER_BINARY'),
        'browser_enabled' => env('SCRAPING_BROWSER_ENABLED', true),
        'ca_bundle' => env('SCRAPING_CA_BUNDLE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
