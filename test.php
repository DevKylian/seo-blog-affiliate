<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$builder = app(\App\Services\EditorialPlanBuilder::class);
$reflection = new \ReflectionClass($builder);
$method1 = $reflection->getMethod('informationalNeutralityIssue');
$method1->setAccessible(true);
$method2 = $reflection->getMethod('placeholderIssue');
$method2->setAccessible(true);

$project = new \App\Models\SeoProject(['name' => 'Indy']);

$bp1 = ['content_type' => 'informational', 'title' => 'Le meilleur guide pour Indy'];
echo "Neutrality Issue (meilleur): " . ($method1->invoke($builder, $project, $bp1) ?? 'NULL') . "\n";

$bp2 = ['content_type' => 'informational', 'title' => 'Guide complet sur la TVA'];
echo "Neutrality Issue (guide): " . ($method1->invoke($builder, $project, $bp2) ?? 'NULL') . "\n";

$bp3 = ['title' => 'Notre Outil est génial', 'angle' => '', 'unique_promise' => ''];
echo "Placeholder Issue (Notre Outil): " . ($method2->invoke($builder, $project, $bp3) ?? 'NULL') . "\n";

$bp4 = ['title' => 'Comment {{brand}} aide', 'angle' => '', 'unique_promise' => ''];
echo "Placeholder Issue ({{): " . ($method2->invoke($builder, $project, $bp4) ?? 'NULL') . "\n";

$bp5 = ['title' => 'Indy est génial', 'angle' => '', 'unique_promise' => ''];
echo "Placeholder Issue (Indy): " . ($method2->invoke($builder, $project, $bp5) ?? 'NULL') . "\n";

