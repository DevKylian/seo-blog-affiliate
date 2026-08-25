<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SeoProject;

class SyncEligibilityData extends Command
{
    protected $signature = 'seo:sync-eligibility';
    protected $description = 'Synchronize eligibility JSON data with SeoProject database records';

    public function handle()
    {
        $dataDir = storage_path('app/tools_data');
        if (!is_dir($dataDir)) {
            $this->error("Directory not found: $dataDir");
            return 1;
        }

        $files = glob($dataDir . '/eligibilite_*_verifiee.json');
        
        foreach ($files as $file) {
            $basename = basename($file);
            // Extract slug
            preg_match('/eligibilite_(.*?)_verifiee\.json/', $basename, $matches);
            if (empty($matches[1])) continue;
            
            $slug = $matches[1];
            $project = SeoProject::where('slug', $slug)->first();
            
            if (!$project) {
                $this->warn("No SeoProject found for slug: $slug");
                continue;
            }
            
            $data = json_decode(file_get_contents($file), true);
            $strengths = [];
            $limitations = [];
            
            if (isset($data['regles_eligibilite'])) {
                foreach ($data['regles_eligibilite'] as $rule) {
                    if ($rule['statut'] === 'GERE') {
                        $strengths[] = $rule['sujet'] . ' : ' . ($rule['detail'] ?? 'Fonctionnalité incluse.');
                    } elseif ($rule['statut'] === 'NON_GERE' || $rule['statut'] === 'GERE_SOUS_CONDITION') {
                        $limitations[] = $rule['sujet'] . ' : ' . ($rule['detail'] ?? 'Non disponible.');
                    }
                }
            }
            
            $project->update([
                'strengths' => $strengths,
                'limitations' => $limitations,
            ]);
            
            $this->info("Updated project: $slug");
        }
        
        $this->info("Synchronization complete.");
        return 0;
    }
}