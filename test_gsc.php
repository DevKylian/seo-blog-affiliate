<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$indexing = app(App\Services\SearchEngineIndexingService::class);
$token = $indexing->googleAccessToken();
$siteUrl = $indexing->googleSiteUrl();

echo "Site URL: " . $siteUrl . "\n";
echo "Encoded Site URL: " . rawurlencode($siteUrl) . "\n";

$endpoint = 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($siteUrl) . '/sitemaps';
$res = Illuminate\Support\Facades\Http::withToken($token)->get($endpoint);
var_dump($res->json());

$endpoint2 = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
$res2 = Illuminate\Support\Facades\Http::timeout(10)->withToken($token)->acceptJson()->asJson()->post($endpoint2, [
    'inspectionUrl' => $siteUrl,
    'siteUrl' => $siteUrl,
    'languageCode' => 'fr-FR',
]);
var_dump($res2->json());
