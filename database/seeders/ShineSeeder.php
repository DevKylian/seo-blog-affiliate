<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\SeoProject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ShineSeeder extends Seeder
{
    public function run(): void
    {
        $project = SeoProject::updateOrCreate(
            ['slug' => 'shine'],
            [
                'name' => 'Shine',
                'website_url' => 'https://www.shine.fr',
                'pricing_url' => 'https://www.shine.fr/prix/',
                'country' => 'FR',
                'currency' => 'EUR',
                'description' => 'Compte Pro en ligne (Néo-banque) avec outil de facturation intégré.',
                'positioning' => 'Idéal pour démarrer sans frais, encaisser par carte et déléguer sa facturation.',
                'features' => [
                    'IBAN français',
                    'Devis et factures illimités',
                    'Dépôt de chèques et d\'espèces',
                    'Relances automatiques',
                    'Cartes virtuelles illimitées'
                ],
                'strengths' => [
                    'Plan gratuit très généreux pour démarrer (0 €/mois).',
                    'Outil de facturation intégré fluide.',
                    'Dépôt d\'espèces (2 à 4% de frais) et de chèques.',
                    'Assurances exclusives (casse écran, hospitalisation).'
                ],
                'limitations' => [
                    'Le plan gratuit est limité à 5 virements par mois (coûteux si beaucoup de fournisseurs).',
                    'Ne génère pas la liasse fiscale (2035/Bilans) comme un logiciel comptable pur.'
                ],
                'best_for' => ['Micro-entreprises', 'Freelances', 'Artisans', 'TPE'],
                'status' => 'active',
                'crawl_status' => 'completed',
            ]
        );

        $plans = [
            [
                'name' => 'Free',
                'position' => 1,
                'monthly_price' => 0,
                'raw_price' => '0 € / mois',
                'features' => ['1 compte', '1 carte Mastercard Basic', '5 virements SEPA/mois', 'Devis/factures illimités']
            ],
            [
                'name' => 'Start',
                'position' => 2,
                'monthly_price' => 11,
                'raw_price' => '11 € HT / mois',
                'features' => ['30 virements SEPA', 'Support prioritaire', 'Assurances basiques']
            ],
            [
                'name' => 'Plus',
                'position' => 3,
                'monthly_price' => 25,
                'raw_price' => '25 € HT / mois',
                'features' => ['5 comptes', '2 cartes Premium', 'Cartes virtuelles illimitées', '100 virements', 'Assurances poussées']
            ],
            [
                'name' => 'Business',
                'position' => 4,
                'monthly_price' => 80,
                'raw_price' => '80 € HT / mois',
                'features' => ['10 comptes', '10 cartes', '500 virements', 'Gestion des accès collaborateurs']
            ],
        ];

        foreach ($plans as $p) {
            Plan::updateOrCreate(
                ['seo_project_id' => $project->id, 'name' => $p['name']],
                [
                    'position' => $p['position'],
                    'monthly_price' => $p['monthly_price'],
                    'currency' => 'EUR',
                    'raw_price' => $p['raw_price'],
                    'billing_period' => 'month',
                    'price_unit' => 'per_user',
                    'is_active' => true,
                    'features' => $p['features'],
                    'verified_at' => now(),
                ]
            );
        }
    }
}
