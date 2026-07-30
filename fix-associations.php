$articles = App\Models\Article::where(function($query) {
    $query->where('title', 'like', '%association%')
          ->orWhere('title', 'like', '%associatif%');
})->where(function($query) {
    $query->where('title', 'like', '%indy%')
          ->orWhere('slug', 'like', '%indy%')
          ->orWhere('primary_keyword', 'like', '%indy%');
})->get();

$deletedArticles = 0;
foreach ($articles as $a) {
    echo "Deleting Article: {$a->title} (ID: {$a->id})\n";
    $a->delete();
    $deletedArticles++;
}

$ideas = App\Models\EditorialIdea::where(function($query) {
    $query->where('title', 'like', '%association%')
          ->orWhere('title', 'like', '%associatif%');
})->where(function($query) {
    $query->where('title', 'like', '%indy%')
          ->orWhere('primary_keyword', 'like', '%indy%');
})->get();

$deletedIdeas = 0;
foreach ($ideas as $idea) {
    echo "Deleting EditorialIdea: {$idea->title} (ID: {$idea->id})\n";
    $idea->delete();
    $deletedIdeas++;
}

echo "Done. Deleted {$deletedArticles} articles and {$deletedIdeas} editorial ideas.\n";
