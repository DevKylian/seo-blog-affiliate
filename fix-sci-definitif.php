<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Article;
use App\Models\Redirect;

echo "--- ETAT ACTUEL EN BASE ---\n";
$articles = Article::whereIn('slug', [
    'blog-guides-generaux-comptabilite-sci',
    'gerer-comptabilite-dune-sci-faire'
])->get(['id', 'slug', 'status', 'updated_at']);

foreach ($articles as $a) {
    echo "ID: {$a->id} | Slug: {$a->slug} | Status: {$a->status} | Updated: {$a->updated_at}\n";
}

echo "\n--- CORRECTION EN COURS ---\n";

$correct = Article::where('slug', 'blog-guides-generaux-comptabilite-sci')->first();
$wrong = Article::where('slug', 'gerer-comptabilite-dune-sci-faire')->first();

if (!$correct) {
    echo "ERREUR: blog-guides-generaux-comptabilite-sci n'existe pas en base.\n";
} else {
    echo "Le pilier 'blog-guides-generaux-comptabilite-sci' a été trouvé (ID: {$correct->id}). On s'assure qu'il est publié...\n";
    $correct->update(['status' => 'published', 'canonical_article_id' => null, 'duplicate_status' => null]);
}

if ($wrong && $correct) {
    echo "Le doublon 'gerer-comptabilite-dune-sci-faire' existe (ID: {$wrong->id}). On le supprime (hard delete) et on crée la 301.\n";
    
    $wrongPath = parse_url($wrong->public_path, PHP_URL_PATH);
    $correctPath = parse_url($correct->public_path, PHP_URL_PATH);
    
    // Supprime l'ancienne 301 inversée au cas où
    Redirect::where('from_path', $correctPath)->delete();
    
    Redirect::updateOrCreate(
        ['from_path' => $wrongPath],
        ['to_path' => $correctPath, 'status_code' => 301, 'active' => true]
    );
    echo "Redirection 301 de {$wrongPath} vers {$correctPath} configurée en base.\n";
    
    // Redirection de l'ancien doublon aussi (gerer-comptabilite-dune-sci-logiciel)
    $dummyArticle = new Article(['type' => 'blog', 'slug' => 'gerer-comptabilite-dune-sci-logiciel']);
    $oldDuplicatePath = parse_url($dummyArticle->public_path, PHP_URL_PATH);
    Redirect::updateOrCreate(
        ['from_path' => $oldDuplicatePath],
        ['to_path' => $correctPath, 'status_code' => 301, 'active' => true]
    );
    
    $wrong->delete();
    echo "Doublon 'gerer-comptabilite-dune-sci-faire' supprimé (hard-delete).\n";
} elseif (!$wrong) {
    echo "Le doublon n'existe pas en base, rien à supprimer.\n";
}

echo "\n--- ETAT DES REDIRECTIONS ---\n";
$redirects = Redirect::whereIn('to_path', ['/gerer-comptabilite-dune-sci-faire', '/blog-guides-generaux-comptabilite-sci'])
    ->orWhereIn('from_path', ['/gerer-comptabilite-dune-sci-faire', '/blog-guides-generaux-comptabilite-sci'])
    ->get();

foreach ($redirects as $r) {
    echo "Redirect ID {$r->id}: {$r->from_path} -> {$r->to_path} ({$r->status_code})\n";
}
echo "Terminé.\n";
