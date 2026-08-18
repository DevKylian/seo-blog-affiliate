<?php
namespace App\Services\EditorialPipeline\Agents;
class PrioritizationAgent extends BaseAgent {
    public function getName(): string { return 'prioritization'; }
    protected function process(array $inputData = []): array
    {
        $clusters = $inputData['clusters'] ?? [];
        
        foreach ($clusters as &$cluster) {
            $volume = $cluster['total_volume'] ?? 0;
            $cluster['opportunity_score'] = min(100, round(($volume / 5000) * 100));
        }
        
        usort($clusters, fn($a, $b) => ($b['opportunity_score'] ?? 0) <=> ($a['opportunity_score'] ?? 0));

        return [
            'prioritized_clusters' => $clusters,
            'status' => 'prioritized'
        ];
    }
}
