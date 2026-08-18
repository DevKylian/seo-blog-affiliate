<?php
namespace App\Services\EditorialPipeline\Agents;
class ConversionAgent extends BaseAgent {
    public function getName(): string { return 'conversion'; }
    protected function process(array $inputData = []): array
    {
        $articles = $inputData['articles'] ?? [];
        
        foreach ($articles as &$article) {
            $article['cta_strategy'] = [
                'primary_offer' => 'Essai gratuit 30 jours',
                'placement' => 'Après le premier H2 et conclusion',
                'angle' => 'Gagnez du temps sur votre gestion'
            ];
        }

        return [
            'articles_with_conversion' => $articles,
            'status' => 'conversion_mapped'
        ];
    }
}
