<?php

namespace App\Services\EditorialPipeline\Agents;

class ImportAgent extends BaseAgent
{
    public function getName(): string
    {
        return 'import';
    }

    protected function process(array $inputData = []): array
    {
        // This agent receives the raw CSV data (or path to it) and cleans it.
        // It validates columns, normalizes, deduplicates.
        
        return [
            'keywords' => $inputData['keywords'] ?? [], // Mocked
            'status' => 'cleaned_and_ready'
        ];
    }
}
