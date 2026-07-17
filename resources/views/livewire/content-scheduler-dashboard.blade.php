@php
    $statusLabels = [
        'queued' => 'En attente', 'planning' => 'Planification', 'generating' => 'Génération',
        'retrying' => 'Nouvelle tentative', 'review' => 'À relire', 'published' => 'Publié',
        'failed' => 'Erreur', 'cancelled' => 'Annulé', 'accepted' => 'Retenue',
        'reserve' => 'Réserve', 'candidate' => 'Analyse', 'generated' => 'Générée',
    ];
@endphp
<div class="dashboard-page factory-page" wire:poll.10s.keep-alive>
    <section class="page-heading factory-heading">
        <div><span class="eyebrow dark">Production continue</span><h1>Content Factory</h1><p>Chargez vos mots-clés une fois : l’application planifie, rédige, maille et livre les contenus au rythme choisi.</p></div>
        <span class="factory-live {{ $schedule && $schedule->is_active ? 'on' : 'off' }}"><i></i>{{ $schedule && $schedule->is_active ? 'Production active' : 'Production en pause' }}</span>
    </section>

    @if($message)<div class="alert success">✓ {{ $message }}</div>@endif
    @if($error)<div class="alert danger">{{ $error }}</div>@endif
    @if(!$hasApiKey)<a class="setup-banner" href="{{ route('admin.settings') }}" wire:navigate><span>◇</span><div><strong>Clé Gemini requise</strong><p>Ajoutez votre clé avant d’activer la production autonome.</p></div><b>Configurer →</b></a>@endif

    @if(!$hasSemrushKey)<a class="setup-banner" href="{{ route('admin.settings') }}" wire:navigate><span>API</span><div><strong>Cle Semrush optionnelle</strong><p>Ajoutez-la pour analyser automatiquement les keyword seeds du cluster Facturation.</p></div><b>Configurer</b></a>@endif

    <section class="factory-stats">
        <div><span class="factory-stat-icon queued">⌛</span><p><strong>{{ $stats['queued'] }}</strong><small>En file</small></p></div>
        <div><span class="factory-stat-icon active">✦</span><p><strong>{{ $stats['active'] }}</strong><small>En production</small></p></div>
        <div><span class="factory-stat-icon review">✓</span><p><strong>{{ $stats['ready'] }}</strong><small>Prêts à relire</small></p></div>
        <div><span class="factory-stat-icon published">↗</span><p><strong>{{ $stats['published'] }}</strong><small>Publiés</small></p></div>
        <div><span class="factory-stat-icon failed">!</span><p><strong>{{ $stats['failed'] }}</strong><small>À contrôler</small></p></div>
    </section>

    @if($latestPlan)
        <article class="panel factory-plan-panel">
            <div class="panel-head"><div><h2>Plan éditorial généré</h2><p>Ce sont ces angles qualifiés — et jamais les mots-clés bruts — qui alimentent le calendrier.</p></div><span class="factory-plan-state {{ $latestPlan->status }}">{{ $latestPlan->status === 'planning' ? 'Planification en cours…' : $latestPlan->accepted_count.' idée(s) retenue(s)' }}</span></div>
            @if($latestPlan->status === 'planning' && $latestPlan->ideas->isEmpty())<div class="factory-planning"><i></i><div><strong>Gemini construit les titres et les angles</strong><p>La page se met à jour automatiquement. Le calendrier sera rempli uniquement après déduplication et validation du plan.</p></div></div>@endif
            @if($latestPlan->ideas->isNotEmpty())<div class="table-wrap"><table><thead><tr><th>#</th><th>Idée éditoriale</th><th>Intention</th><th>Angle / audience</th><th>Score</th><th>Similarité</th><th>Statut</th></tr></thead><tbody>
                @foreach($latestPlan->ideas as $idea)<tr>
                    <td>{{ $idea->position ?: $loop->iteration }}</td>
                    <td><strong class="factory-idea-title">{{ $idea->title }}</strong><div class="factory-idea-keyword"><b>{{ $idea->keyword?->hasMeasuredDifficulty() ? 'KD '.round($idea->keyword->keyword_difficulty) : 'KD —' }}</b>{{ $idea->primary_keyword }}</div></td>
                    <td>{{ $idea->intent }}</td>
                    <td><strong class="factory-angle">{{ str_replace('-',' ',$idea->angle) }}</strong><small>{{ str_replace('-',' ',$idea->audience) }}</small></td>
                    <td><b>{{ number_format($idea->seo_score,1,',',' ') }}</b></td>
                    <td>{{ number_format($idea->similarity_score,0,',',' ') }} %</td>
                    <td><span class="factory-status {{ $idea->status }}"><i></i>{{ $statusLabels[$idea->status] ?? $idea->status }}</span></td>
                </tr>@endforeach
            </tbody></table></div>@endif
        </article>
    @endif

    <section class="factory-layout">
        <article class="panel factory-config">
            <div class="panel-head"><div><h2>Cadence de production</h2><p>Les nouveaux mots-clés ajoutés plus tard rejoindront automatiquement la file.</p></div></div>
            <form class="os-form one-column" wire:submit="saveFactory">
                <div class="field"><label>Projet</label><select wire:model.live="projectId"><option value="">Sélectionner</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }} · {{ number_format($project->keywords_count,0,',',' ') }} mots-clés</option>@endforeach</select>@error('projectId')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Liste Semrush à ajouter maintenant — facultatif</label><textarea wire:model="pastedKeywords" rows="7" placeholder="Mot clé&#9;Intention&#9;Volume&#9;KD %&#9;CPC (EUR)&#10;logiciel devis facture&#9;C&#9;2400&#9;54&#9;6,02"></textarea><p class="field-help">Collez la ligne d’en-tête et le tableau. Les doublons sont mis à jour et seuls les sujets encore inexploités sont programmés.</p>@error('pastedKeywords')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Export Semrush CSV à charger — facultatif</label><input wire:model="csv" type="file" accept=".csv,.txt,text/csv,text/tab-separated-values"><p class="field-help">Le fichier est fusionné avec la base existante, puis les clusters sont recalculés automatiquement.</p>@error('csv')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="factory-rate-row">
                    <div class="field"><label>Articles par semaine</label><input wire:model="articlesPerWeek" type="number" min="1" max="7"><p class="field-help">Un article maximum par jour, réparti du lundi au dimanche.</p></div>
                    <label class="factory-switch"><input wire:model="autoPublish" type="checkbox"><span></span><b>Publication automatique<small>Sinon, les articles arrivent en brouillon « À relire ».</small></b></label>
                </div>
                <div class="field"><label>Directives permanentes — facultatif</label><textarea wire:model="instructions" rows="4" placeholder="Ton, audience, contraintes éditoriales propres à ce projet…"></textarea></div>
                <div class="factory-actions">
                    <button class="primary-button" type="submit"><span wire:loading.remove wire:target="saveFactory">Activer et remplir le calendrier</span><span wire:loading wire:target="saveFactory">Mise en place…</span></button>
                    @if($schedule)<button class="secondary-link button-link" type="button" wire:click="toggleFactory">{{ $schedule->is_active ? 'Mettre en pause' : 'Reprendre la production' }}</button>@endif
                </div>
                <button class="factory-batch-button" type="button" wire:click="expandFacturationSeeds" wire:confirm="Appeler Semrush pour enrichir le cluster Facturation ?"><span wire:loading.remove wire:target="expandFacturationSeeds">Analyser Facturation avec Semrush</span><span wire:loading wire:target="expandFacturationSeeds">Analyse Semrush...</span></button>
                @if($schedule)<button class="factory-batch-button" type="button" wire:click="generateWeek"><span wire:loading.remove wire:target="generateWeek">⚡ Générer la semaine ({{ $schedule->articles_per_week }} contenus)</span><span wire:loading wire:target="generateWeek">Préparation du lot…</span></button>@endif
                @if($schedule && $stats['queued'] > 0)<button class="factory-batch-button factory-generate-now" type="button" wire:click="generateAllNow" wire:confirm="Générer maintenant les {{ $stats['queued'] }} contenus actuellement prévus ?"><span wire:loading.remove wire:target="generateAllNow">▶ Générer maintenant les {{ $stats['queued'] }} contenus prévus</span><span wire:loading wire:target="generateAllNow">Lancement en arrière-plan…</span></button>@endif
            </form>
        </article>

        <article class="panel factory-calendar-panel">
            <div class="factory-calendar-head">
                <div><h2>Calendrier éditorial</h2><p>Glissez un contenu en attente sur une autre date pour le reprogrammer.</p></div>
                <div><button wire:click="previousMonth" type="button" aria-label="Mois précédent">←</button><strong>{{ ucfirst($calendarTitle) }}</strong><button wire:click="nextMonth" type="button" aria-label="Mois suivant">→</button></div>
            </div>
            <div class="factory-weekdays">@foreach(['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'] as $weekday)<span>{{ $weekday }}</span>@endforeach</div>
            <div class="factory-calendar">
                @foreach($days as $day)
                    <div class="factory-day {{ !$day['current'] ? 'outside' : '' }} {{ $day['today'] ? 'today-cell' : '' }}"
                         @dragover.prevent="$el.classList.add('drop-target')"
                         @dragleave="$el.classList.remove('drop-target')"
                         @drop.prevent="$el.classList.remove('drop-target'); $wire.moveTask(Number($event.dataTransfer.getData('text/plain')), '{{ $day['date'] }}')">
                        <span class="factory-day-number">{{ $day['day'] }}</span>
                        <div class="factory-events">
                            @foreach($day['tasks'] as $task)
                                <div class="factory-event {{ $task->status }}" @if(in_array($task->status,['queued','retrying'])) draggable="true" @dragstart="$event.dataTransfer.setData('text/plain','{{ $task->id }}')" @endif title="{{ $task->editorialIdea?->title }}">
                                    <small>{{ $task->scheduled_for?->format('H:i') }} · {{ $task->keyword?->hasMeasuredDifficulty() ? 'KD '.round($task->keyword->keyword_difficulty) : 'KD —' }}</small>
                                    <strong>{{ $task->editorialIdea?->title ?: 'Brief en préparation' }}</strong>
                                    @if($task->article)<a href="{{ route('admin.articles.edit',$task->article) }}" wire:navigate>Ouvrir →</a>@endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <article class="panel factory-queue-panel">
        <div class="panel-head"><div><h2>File de production</h2><p>Statuts en temps réel, tentatives automatiques et journal de chaque mot-clé.</p></div><span class="factory-cron-note">Actualisation toutes les 10 s</span></div>
        <div class="table-wrap"><table><thead><tr><th>Programmation</th><th>Idée éditoriale</th><th>SEO</th><th>Statut</th><th>Tentatives</th><th>Détail</th><th>Actions</th></tr></thead><tbody>
            @forelse($queue as $task)
                <tr>
                    <td><strong class="table-title">{{ $task->scheduled_for?->translatedFormat('d M Y') }}</strong><br><span>{{ $task->scheduled_for?->format('H:i') }}</span></td>
                    <td><strong class="factory-keyword">{{ $task->editorialIdea?->title ?: 'Brief éditorial indisponible' }}</strong><small class="factory-source-keyword">{{ $task->keyword?->keyword ?: 'Mot-clé supprimé' }}</small></td>
                    <td><span class="strategy-badge {{ $task->keyword?->strategy_tier ?? 'supporting' }}">{{ ['pillar'=>'Pilier','quick_win'=>'Quick win','niche'=>'Niche','supporting'=>'Support'][$task->keyword?->strategy_tier ?? 'supporting'] }}</span><br><small>KD {{ $task->keyword?->hasMeasuredDifficulty() ? round($task->keyword->keyword_difficulty) : '—' }}</small></td>
                    <td><span class="factory-status {{ $task->status }}"><i></i>{{ $statusLabels[$task->status] ?? $task->status }}</span>@if($task->retry_at)<small class="factory-retry">Retry {{ $task->retry_at->diffForHumans() }}</small>@endif</td>
                    <td>{{ $task->attempts }}</td>
                    <td class="factory-error">{{ $task->error_message ? \Illuminate\Support\Str::limit($task->error_message,90) : '—' }}</td>
                    <td><div class="factory-row-actions">
                        @if($task->article)<a class="table-action" href="{{ route('admin.articles.edit',$task->article) }}" wire:navigate>Relire</a>@endif
                        @if(in_array($task->status,['queued','retrying']))<button type="button" wire:click="generateTaskNow({{ $task->id }})">Générer maintenant</button><button class="danger" type="button" wire:click="cancelTask({{ $task->id }})" wire:confirm="Ne pas générer ce contenu ?">Ne pas générer</button>@endif
                        @if($task->status === 'failed')<button type="button" wire:click="retryTask({{ $task->id }})">Réessayer</button>@endif
                    </div></td>
                </tr>
            @empty<tr><td colspan="7" class="empty-state">Activez l’usine pour répartir les mots-clés inexploités sur le calendrier.</td></tr>@endforelse
        </tbody></table></div>
    </article>
</div>
