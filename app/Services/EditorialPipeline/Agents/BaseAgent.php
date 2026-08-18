<?php

namespace App\Services\EditorialPipeline\Agents;

use App\Models\EditorialPipeline;
use App\Models\PipelineArtifact;
use Exception;

abstract class BaseAgent
{
    protected EditorialPipeline $pipeline;
    
    public function __construct(EditorialPipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /**
     * Get the name of this agent (e.g., 'strategy', 'import', 'clustering').
     */
    abstract public function getName(): string;

    /**
     * Execute the agent's logic.
     * @param array $inputData Data provided by the previous agent or human input.
     * @return array The output data of this agent.
     */
    abstract protected function process(array $inputData = []): array;

    /**
     * Run the agent and save the resulting artifact.
     */
    public function run(array $inputData = []): PipelineArtifact
    {
        try {
            // Update pipeline status
            $this->pipeline->update([
                'current_agent' => $this->getName(),
                'status' => 'processing',
                'error_message' => null,
            ]);

            // Process data
            $outputData = $this->process($inputData);

            // Determine output version (increment based on previous runs)
            $previousArtifactsCount = $this->pipeline->pipelineArtifacts()
                ->where('agent_name', $this->getName())
                ->count();
            
            $version = $previousArtifactsCount + 1;

            // Save the artifact
            $artifact = $this->pipeline->pipelineArtifacts()->create([
                'agent_name' => $this->getName(),
                'output_version' => $version,
                'status' => 'validated',
                'data' => $outputData,
            ]);

            return $artifact;

        } catch (Exception $e) {
            $this->pipeline->update([
                'status' => 'error',
                'error_message' => $e->getMessage() . "\n" . $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
