<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$p = \App\Models\SeoProject::where('name', 'like', '%Indy%')->first();
$all = $p->keywords()->orderBy('id')->pluck('keyword')->toArray();
$recent = $p->keywords()->latest('created_at')->limit(10)->pluck('keyword')->toArray();
echo json_encode(['count' => count($all), 'first_10' => array_slice($all, 0, 10), 'recent_10' => $recent], JSON_PRETTY_PRINT);
