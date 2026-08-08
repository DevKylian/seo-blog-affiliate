<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Keyword;
use App\Models\EditorialIdea;

final class FixInterpreteurAugust extends Command
{
    protected $signature = 'fix:interpreteur-august';
    protected $description = 'Supprime le mot-clé hors-sujet interpreteur comptable';

    public function handle(): void
    {
        $this->info('Recherche du mot-clé "interpreteur"...');

        $deletedCount = 0;

        $keywords = Keyword::where('keyword', 'like', "%interpreteur%")->get();
        
        foreach ($keywords as $kw) {
            $ideas = EditorialIdea::where('keyword_id', $kw->id)->delete();
            $kwName = $kw->keyword;
            $kw->delete();
            $deletedCount++;
            
            $this->line("- Supprimé : {$kwName} (id: {$kw->id})");
        }

        $this->info("Terminé. {$deletedCount} mots-clés supprimés.");
    }
}
