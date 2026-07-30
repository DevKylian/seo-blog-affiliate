<?php

use App\Models\Article;
use App\Models\EditorialIdea;

// Initialize Laravel environment
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting associations purge...\n";

$articles = Article::where('title', 'like', '%association%')
    ->orWhere('title', 'like', '%associatif%')
    ->orWhere('primary_keyword', 'like', '%association%')
    ->orWhere('primary_keyword', 'like', '%associatif%')
    ->orWhere('slug', 'like', '%association%')
    ->orWhere('slug', 'like', '%associatif%')
    ->get();

$deletedArticles = 0;
foreach ($articles as $a) {
    echo "Deleting Article: {$a->title} (ID: {$a->id})\n";
    $a->delete();
    $deletedArticles++;
}

$ideas = EditorialIdea::where('title', 'like', '%association%')
    ->orWhere('title', 'like', '%associatif%')
    ->orWhere('primary_keyword', 'like', '%association%')
    ->orWhere('primary_keyword', 'like', '%associatif%')
    ->get();

$deletedIdeas = 0;
foreach ($ideas as $idea) {
    echo "Deleting EditorialIdea: {$idea->title} (ID: {$idea->id})\n";
    $idea->delete();
    $deletedIdeas++;
}

echo "Done. Deleted {$deletedArticles} articles and {$deletedIdeas} editorial ideas.\n";
