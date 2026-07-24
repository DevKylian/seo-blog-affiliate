<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = \App\Models\SeoProject::where('slug', 'indy')->first();
if ($p) {
    $p->description = "Indy est une solution complète de comptabilité et de facturation conçue spécialement pour les freelances et indépendants. Elle automatise la saisie comptable grâce à la synchronisation bancaire.";
    $p->positioning = "L'alternative la plus simple pour gérer la comptabilité des freelances sans expert-comptable.";
    $p->features = ["Synchronisation bancaire en temps réel", "Édition de devis et factures illimités", "Calcul automatisé des cotisations URSSAF et de la TVA", "Génération des déclarations fiscales (2035, 2042 C PRO...)", "Support client ultra-réactif basé en France", "Application mobile (iOS/Android)", "Pilotage en temps réel avec un tableau de bord", "Accompagnement à la création d'entreprise gratuit"];
    $p->strengths = ["Gain de temps massif sur la saisie comptable", "Interface moderne et très intuitive", "Tarif gratuit à vie pour la facturation", "Tarif très compétitif par rapport à un expert-comptable classique"];
    $p->limitations = ["Ne convient pas aux PME avec des salariés", "Gestion de stocks inexistante", "Impossible de personnaliser les documents à 100% (pas de CSS custom)"];
    $p->save();
    echo "Indy updated.\n";
} else {
    echo "Indy not found.\n";
}
