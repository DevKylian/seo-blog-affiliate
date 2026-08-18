<?php

namespace App\Services\EditorialPipeline\Agents;

class ClusteringAgent extends BaseAgent
{
    public function getName(): string
    {
        return 'clustering';
    }

    protected function process(array $inputData = []): array
    {
        $keywords = $inputData['keywords'] ?? [];
        $clusters = [];
        
        foreach ($keywords as $kw) {
            $intent = $kw['intent'] ?? 'Informational';
            if (!isset($clusters[$intent])) {
                $clusters[$intent] = [
                    'main_keyword' => $kw['keyword'] ?? 'Unknown',
                    'intent' => $intent,
                    'total_volume' => 0,
                    'keywords' => []
                ];
            }
            $clusters[$intent]['keywords'][] = $kw['keyword'] ?? '';
            $clusters[$intent]['total_volume'] += $kw['volume'] ?? 0;
        }

        return [
            'clusters' => array_values($clusters),
            'status' => 'clustered'
        ];
    }
}
