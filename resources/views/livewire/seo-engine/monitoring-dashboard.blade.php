<div>
    <div class="panel-head" style="border:none; padding-bottom: 0;">
        <h2>Monitoring SEO & Amélioration Continue</h2>
        <p>Liste des anomalies détectées et actions recommandées par le moteur.</p>
    </div>

    <div style="padding: 20px; display: grid; gap: 14px;">
        @forelse($tasks as $task)
            <div class="panel {{ $task->priority > 2 ? 'danger-panel' : '' }}" style="display: flex; justify-content: space-between; align-items: center; padding: 16px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <span class="state-badge {{ $task->priority > 2 ? 'failed' : 'scheduled' }}" style="background: {{ $task->priority > 2 ? '#fff0f2' : '#fff1df' }}; color: {{ $task->priority > 2 ? '#a63b4c' : '#b36e20' }}; border: 1px solid {{ $task->priority > 2 ? '#f1c4cc' : '#f0e0cc' }};">
                            Priorité {{ $task->priority }}
                        </span>
                        <strong style="font-size: 11px; color: var(--ink);">{{ $task->url }}</strong>
                    </div>
                    <p style="margin: 4px 0 0; font-size: 11px; color: #495268; font-weight: 600;">
                        Action : {{ str_replace('_', ' ', \Illuminate\Support\Str::title($task->action_type)) }}
                    </p>
                    @if($task->metrics_snapshot)
                        <div style="margin-top: 8px; font-size: 9px; color: #9aa2b5;">
                            Impressions: {{ $task->metrics_snapshot['impressions'] ?? 'N/A' }} | 
                            Clics: {{ $task->metrics_snapshot['clicks'] ?? 'N/A' }} | 
                            Position: {{ $task->metrics_snapshot['position'] ?? 'N/A' }}
                        </div>
                    @endif
                </div>
                <div>
                    <button wire:click="resolveTask({{ $task->id }})" class="primary-button" style="min-height: 36px; padding: 0 16px; font-size: 9px;">
                        Générer avec l'IA
                    </button>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <span style="font-size: 24px; color: #a1a9b9;">✅</span>
                <p style="margin-top: 10px; font-size: 11px; color: #6f7890;">Aucune anomalie détectée. Tout fonctionne parfaitement !</p>
            </div>
        @endforelse
    </div>
</div>
