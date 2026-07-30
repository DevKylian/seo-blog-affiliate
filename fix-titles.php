$articles = App\Models\Article::where('title', 'like', '%Tarifs et prix%')->get();
$count = 0;
foreach ($articles as $a) {
    $a->title = str_replace('Tarifs et prix', 'Tarifs', $a->title);
    
    if ($a->meta_title) {
        $a->meta_title = str_replace('Tarifs et prix', 'Tarifs', $a->meta_title);
    }
    
    if ($a->thumbnail_title) {
        $a->thumbnail_title = str_replace('Tarifs et prix', 'Tarifs', $a->thumbnail_title);
    }
    
    $a->save();
    $count++;
    echo "Updated Article: {$a->title}\n";
}
echo "Updated {$count} articles.\n";

$ideas = App\Models\EditorialIdea::where('title', 'like', '%Tarifs et prix%')->get();
$ideaCount = 0;
foreach ($ideas as $idea) {
    $idea->title = str_replace('Tarifs et prix', 'Tarifs', $idea->title);
    
    if ($idea->thumbnail_title) {
        $idea->thumbnail_title = str_replace('Tarifs et prix', 'Tarifs', $idea->thumbnail_title);
    }
    
    $idea->save();
    $ideaCount++;
    echo "Updated EditorialIdea: {$idea->title}\n";
}
echo "Updated {$ideaCount} editorial ideas.\n";
