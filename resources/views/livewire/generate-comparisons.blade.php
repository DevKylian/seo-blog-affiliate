<div style="padding: 30px;">
    
    <!-- En-tête -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 800; color: #f8fafc; margin: 0 0 6px;">⚡ Générateur de Comparatifs IA</h1>
            <p style="color: #94a3b8; font-size: 14px; margin: 0;">Générez tous les duels et comparatifs entre logiciels en 1 clic pour votre stratégie d'affiliation.</p>
        </div>

        <button wire:click="generateAllMissing" wire:loading.attr="disabled"
                style="background: linear-gradient(135deg, #6366f1, #4f46e5); color: #ffffff; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 800; font-size: 15px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(99,102,241,0.35); transition: transform 0.2s;">
            <span wire:loading.remove>✨ Générer TOUS les comparatifs manquants (1-Click)</span>
            <span wire:loading>⏳ Lancement en cours...</span>
        </button>
    </div>

    @if($message)
        <div style="padding: 14px 20px; border-radius: 10px; margin-bottom: 24px; font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 10px;
                    background: {{ $messageType === 'success' ? 'rgba(34, 197, 94, 0.15)' : ($messageType === 'error' ? 'rgba(239, 68, 68, 0.15)' : 'rgba(59, 130, 246, 0.15)') }};
                    color: {{ $messageType === 'success' ? '#4ade80' : ($messageType === 'error' ? '#f87171' : '#60a5fa') }};
                    border: 1px solid {{ $messageType === 'success' ? '#22c55e' : ($messageType === 'error' ? '#ef4444' : '#3b82f6') }};">
            <span>{{ $message }}</span>
        </div>
    @endif

    <!-- Cartes Statistiques -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">
        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 14px; padding: 20px;">
            <span style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Total des duels identifiés</span>
            <div style="font-size: 32px; font-weight: 800; color: #f8fafc; margin-top: 8px;">{{ $totalCount }}</div>
        </div>

        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 14px; padding: 20px;">
            <span style="font-size: 12px; font-weight: 700; color: #4ade80; text-transform: uppercase; letter-spacing: 0.05em;">En ligne & Publiés</span>
            <div style="font-size: 32px; font-weight: 800; color: #4ade80; margin-top: 8px;">{{ $publishedCount }}</div>
        </div>

        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 14px; padding: 20px;">
            <span style="font-size: 12px; font-weight: 700; color: #f87171; text-transform: uppercase; letter-spacing: 0.05em;">Manquants à générer</span>
            <div style="font-size: 32px; font-weight: 800; color: #f87171; margin-top: 8px;">{{ $missingCount }}</div>
        </div>
    </div>

    <!-- Tableau des Comparatifs -->
    <div style="background: #1e293b; border: 1px solid #334155; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div style="padding: 20px 24px; border-bottom: 1px solid #334155; display: flex; align-items: center; justify-content: space-between;">
            <h2 style="font-size: 18px; font-weight: 800; color: #f8fafc; margin: 0;">Matrice des comparatifs</h2>
            <span style="font-size: 13px; color: #94a3b8;">Cliquez sur générer pour créer un article spécifique</span>
        </div>

        <table style="width: 100%; border-collapse: collapse; text-align: left; color: #cbd5e1;">
            <thead>
                <tr style="background: #0f172a; border-bottom: 1px solid #334155; font-size: 12px; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em;">
                    <th style="padding: 16px 24px;">Duel / Titre</th>
                    <th style="padding: 16px 24px;">Projet de base</th>
                    <th style="padding: 16px 24px;">Statut</th>
                    <th style="padding: 16px 24px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($duels as $duel)
                    <tr style="border-bottom: 1px solid #334155;">
                        <td style="padding: 16px 24px;">
                            <strong style="font-size: 15px; color: #f8fafc;">{{ $duel['title'] }}</strong>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">/comparatifs/{{ $duel['slug'] }}</div>
                        </td>
                        <td style="padding: 16px 24px;">
                            <span style="display: inline-block; padding: 4px 10px; border-radius: 6px; background: #334155; color: #e2e8f0; font-size: 12px; font-weight: 700;">
                                {{ $duel['project']->name }}
                            </span>
                        </td>
                        <td style="padding: 16px 24px;">
                            @if($duel['status'] === 'published')
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: rgba(34, 197, 94, 0.15); color: #4ade80; font-size: 12px; font-weight: 700;">
                                    ● En ligne
                                </span>
                            @else
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: rgba(239, 68, 68, 0.15); color: #f87171; font-size: 12px; font-weight: 700;">
                                    ○ Non généré
                                </span>
                            @endif
                        </td>
                        <td style="padding: 16px 24px; text-align: right;">
                            @if($duel['status'] === 'published')
                                <a href="{{ route('comparisons.show', $duel['slug']) }}" target="_blank"
                                   style="color: #60a5fa; text-decoration: none; font-size: 13px; font-weight: 700;">
                                    Voir l'article ↗
                                </a>
                            @else
                                <button wire:click="generateSingle('{{ $duel['project']->slug }}', '{{ $duel['competitor'] }}')"
                                        wire:loading.attr="disabled"
                                        style="background: #2563eb; color: white; border: none; padding: 8px 14px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer;">
                                    ✨ Générer
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
