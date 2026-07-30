$articles = App\Models\Article::all();
$slugCount = 0;
$linkCount = 0;

foreach ($articles as $a) {
    $changed = false;

    if ($a->type === 'comparison' && !str_contains($a->slug, '-vs-')) {
        $parts = explode('-', $a->slug, 2);
        if (count($parts) == 2) {
            $newSlug = $parts[0] . '-vs-' . $parts[1];
            $a->slug = $newSlug;
            $changed = true;
            $slugCount++;
            echo "Updated slug for {$a->title} to {$newSlug}\n";
        }
    }

    if (str_contains((string)$a->body, 'businesskit.test') || str_contains((string)$a->body, 'http://businesskit.fr') || str_contains((string)$a->body, 'https://businesskit.fr')) {
        $a->body = str_replace(
            ['http://businesskit.test', 'https://businesskit.test', 'businesskit.test', 'http://businesskit.fr', 'https://businesskit.fr'],
            ['https://www.businesskit.fr', 'https://www.businesskit.fr', 'www.businesskit.fr', 'https://www.businesskit.fr', 'https://www.businesskit.fr'],
            $a->body
        );
        $changed = true;
        $linkCount++;
        echo "Updated links in body for {$a->title}\n";
    }

    if ($changed) {
        $a->save();
    }
}
echo "Done. Updated {$slugCount} slugs and {$linkCount} bodies.\n";
