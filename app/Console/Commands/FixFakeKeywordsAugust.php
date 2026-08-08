<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Keyword;
use App\Models\EditorialIdea;

final class FixFakeKeywordsAugust extends Command
{
    protected $signature = 'fix:fake-keywords-august';
    protected $description = 'Supprime les mots-clés fictifs générés par l’IA (InvoiceMaster, etc.)';

    public function handle(): void
    {
        $this->info('Recherche de mots-clés fictifs...');

        $fakeBrands = [
            'invoicemaster',
            'invoiceflow',
            'quotepro',
            'easycompta',
            'financecore',
            'financepro',
            'legalinvoice',
            'quickbill',
            'accountpro',
            'accountpool',
            'cashmaster',
            'digicube',
        ];

        $deletedCount = 0;

        foreach ($fakeBrands as $brand) {
            $keywords = Keyword::where('keyword', 'like', "%{$brand}%")->get();
            
            foreach ($keywords as $kw) {
                // Delete associated Editorial Ideas
                $ideas = EditorialIdea::where('keyword_id', $kw->id)->delete();
                
                // We do not delete Articles here, assuming they are not published, but just in case:
                // if there are articles, we should probably know, but the user only mentioned keywords.
                
                $kwName = $kw->keyword;
                $kw->delete();
                $deletedCount++;
                
                $this->line("- Supprimé : {$kwName} (id: {$kw->id})");
            }
        }

        $this->info("Terminé. {$deletedCount} mots-clés fictifs supprimés.");
    }
}
