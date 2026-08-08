<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$planId = 168; // Le plan que l'on veut tester
$plan = App\Models\EditorialPlan::find($planId);

if (!$plan) {
    die("Plan $planId introuvable.\n");
}

echo "Plan trouvé. Remise en statut planning...\n";
$plan->update(['status' => 'planning']);

echo "Lancement direct de EditorialPlanBuilder->advance()...\n";
echo "Veuillez patienter (l'API Gemini peut prendre 10 à 30 secondes)...\n\n";

try {
    $builder = app(App\Services\EditorialPlanBuilder::class);
    $plan = $builder->advance($plan);
    echo "SUCCÈS ! L'étape s'est terminée correctement.\n";
    echo "Nouveau statut du plan : " . $plan->status . "\n";
} catch (\Throwable $e) {
    echo "\nERREUR FATALE DÉTECTÉE :\n";
    echo $e->getMessage() . "\n\n";
    echo "Trace :\n" . $e->getTraceAsString() . "\n";
}
