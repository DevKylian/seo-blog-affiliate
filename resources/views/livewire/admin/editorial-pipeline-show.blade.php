<div class="dashboard-page os-page">
    <section class="page-heading">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="{{ route('admin.editorial-pipeline') }}" wire:navigate style="color: #64748b; text-decoration: none; font-size: 20px;">←</a>
            <div>
                <span class="eyebrow dark">Pipeline Actif</span>
                <h1>{{ $pipeline->theme }}</h1>
                <p>Création de contenu étape par étape via agents IA.</p>
            </div>
        </div>
        <div>
            <span class="state-badge {{ $pipeline->status === 'error' ? 'failed' : '' }}">{{ $pipeline->status }}</span>
        </div>
    </section>

    @php
        $steps = [
            'strategy' => 'Stratégie',
            'import' => 'Import CSV',
            'clustering' => 'Clustering',
            'prioritization' => 'Priorisation',
            'serp' => 'Analyse SERP',
            'architecture' => 'Architecture',
            'conversion' => 'Conversion',
            'brief' => 'Brief',
            'writing' => 'Rédaction',
            'critique' => 'Relecture'
        ];
        $currentStepIndex = array_search($pipeline->current_agent, array_keys($steps));
        if ($currentStepIndex === false && $pipeline->status === 'completed') {
            $currentStepIndex = 999;
        }
    @endphp

    <div class="panel" style="padding: 20px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; overflow-x: auto; padding-bottom: 10px;">
            @foreach($steps as $key => $label)
                @php
                    $index = $loop->index;
                    $isPast = $pipeline->status === 'completed' || $index < $currentStepIndex;
                    $isActive = $index === $currentStepIndex && $pipeline->status !== 'completed';
                    $color = $isPast ? '#16a34a' : ($isActive ? '#4f46e5' : '#cbd5e1');
                    $fontWeight = $isActive ? 'bold' : 'normal';
                @endphp
                <div style="display: flex; flex-direction: column; align-items: center; min-width: 80px;">
                    <div style="width: 24px; height: 24px; border-radius: 50%; background-color: {{ $color }}; color: white; display: flex; align-items: center; justify-content: center; font-size: 12px; margin-bottom: 8px;">
                        @if($isPast) ✓ @else {{ $index + 1 }} @endif
                    </div>
                    <span style="font-size: 11px; color: {{ $isActive ? '#1e293b' : '#64748b' }}; font-weight: {{ $fontWeight }};">{{ $label }}</span>
                </div>
                @if(!$loop->last)
                    <div style="flex: 1; height: 2px; background-color: {{ $isPast ? '#16a34a' : '#cbd5e1' }}; margin: 0 10px; margin-top: -20px;"></div>
                @endif
            @endforeach
        </div>
    </div>

    <article class="panel">
        <div class="panel-head"><div><h2>Action Requise</h2><p>Détails de l'étape en cours</p></div></div>
        
        <div style="padding: 20px;">
            @if($pipeline->current_agent === 'strategy' && $pipeline->status === 'processing')
                @php
                    $artifact = $pipeline->pipelineArtifacts()->where('agent_name', 'strategy')->latest()->first();
                @endphp
                @if($artifact)
                    <h4 style="color: #4338ca; font-weight: bold; margin-bottom: 8px;">1. Cartographie Sémantique Générée par l'IA</h4>
                    
                    <div style="margin-bottom: 20px;">
                        @foreach($artifact->data['themes'] ?? [] as $t)
                            <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #cbd5e1; margin-bottom: 12px;">
                                <h5 style="margin: 0 0 10px 0; color: #1e293b; font-weight: 800; font-size: 14px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">{{ $t['name'] ?? 'Thème' }}</h5>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <strong style="color: #64748b; font-size: 11px; text-transform: uppercase;">Sous-thèmes :</strong>
                                        <ul style="margin-top: 4px; margin-bottom: 0; padding-left: 15px; color: #475569; font-size: 12px;">
                                            @foreach($t['subthemes'] ?? [] as $st)
                                                <li>{{ $st }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div>
                                        <strong style="color: #64748b; font-size: 11px; text-transform: uppercase;">Mots-clés racines (Semrush) :</strong>
                                        <div style="margin-top: 4px; display: flex; flex-wrap: wrap; gap: 4px;">
                                            @foreach($t['seed_keywords'] ?? [] as $sk)
                                                <span style="background: #e0e7ff; color: #3730a3; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600;">{{ $sk }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <h4 style="color: #4338ca; font-weight: bold; margin-bottom: 8px;">2. Action Requise : Import Semrush</h4>
                    <p style="margin-bottom: 10px; color: #475569;">Exportez les données depuis Semrush en cherchant ces mots-clés, avec les colonnes : <code>{{ implode(', ', $artifact->data['required_columns'] ?? []) }}</code> puis uploadez le fichier CSV.</p>
                    
                    <livewire:admin.pipeline-steps.data-request-step :pipelineId="$pipeline->id" :key="'dr-'.$pipeline->id" />
                @endif
            @elseif($pipeline->status === 'completed')
                <div style="font-size: 13px; background: #f0fdf4; padding: 12px; border-radius: 6px; border: 1px solid #bbf7d0;">
                    <h4 style="color: #166534; font-weight: bold; margin-bottom: 8px;">Pipeline terminé avec succès ! 🎉</h4>
                    <p style="color: #15803d;">Tous les agents ont accompli leur tâche. Les brouillons d'articles ont été générés.</p>
                </div>
            @else
                <div style="font-size: 13px;">
                    <h4 style="color: #4338ca; font-weight: bold; margin-bottom: 8px;">Agent IA : {{ ucfirst($pipeline->current_agent) }}</h4>
                    <p style="color: #475569; margin-bottom: 15px;">Le travail de l'agent a abouti. Vous pouvez examiner ses résultats ci-dessous.</p>
                    
                    @php
                        $latestArtifact = $pipeline->pipelineArtifacts()->where('agent_name', $pipeline->current_agent)->latest()->first();
                    @endphp
                    @if($latestArtifact)
                        <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
                            <strong style="color: #334155; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 10px;">Aperçu de l'Artefact Généré :</strong>
                            <pre style="margin: 0; font-size: 12px; white-space: pre-wrap; color: #1e293b; max-height: 300px; overflow-y: auto; background: white; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;">{{ json_encode(array_slice($latestArtifact->data ?? [], 0, 10), JSON_PRETTY_PRINT) }}{{ count($latestArtifact->data ?? []) > 10 ? "\n\n... (données tronquées pour l'aperçu)" : "" }}</pre>
                        </div>
                    @endif
                    
                    <button wire:click="continuePipeline" class="primary-button" style="padding: 10px 20px; font-size: 14px; width: 100%; display: flex; justify-content: center;">
                        Passer à l'agent suivant →
                    </button>
                </div>
            @endif
        </div>
    </article>
</div>
