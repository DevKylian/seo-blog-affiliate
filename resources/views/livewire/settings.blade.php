<div class="dashboard-page os-page settings-page">
    <section class="page-heading settings-heading">
        <div>
            <span class="eyebrow dark">Configuration</span>
            <h1>Réglages API</h1>
            <p>Connecteurs serveur, clés sensibles et indexation automatique au même endroit.</p>
        </div>
        <div class="settings-health" aria-label="État des connecteurs">
            <span class="{{ $hasSavedKey ? 'is-on' : '' }}"><i></i>IA</span>
            <span class="{{ $hasSavedSemrushKey ? 'is-on' : '' }}"><i></i>Semrush</span>
            <span class="{{ $indexingSummary['indexnow_enabled'] ? 'is-on' : '' }}"><i></i>IndexNow</span>
            <span class="{{ $indexingSummary['google_enabled'] ? 'is-on' : '' }}"><i></i>Search Console</span>
            <span class="{{ $searchPerformanceSummary['bing_performance_enabled'] ? 'is-on' : '' }}"><i></i>Bing Performance</span>
        </div>
    </section>

    @if($message)<div class="alert success">{{ $message }}</div>@endif
    @if($error)<div class="alert danger">{{ $error }}</div>@endif

    <form wire:submit="save" class="settings-shell">
        <section class="panel settings-hero-panel">
            <span class="settings-mark">API</span>
            <div>
                <span class="settings-kicker">Centre de contrôle</span>
                <h2>Automatisation, données SEO et indexation</h2>
                <p>Les clés restent chiffrées côté serveur. Les envois vers Google et Bing partent en arrière-plan pour ne pas bloquer le dashboard.</p>
            </div>
            <span class="connection-state {{ $hasSavedKey && $hasSavedSemrushKey ? 'connected':'' }}">
                <i></i>{{ $hasSavedKey && $hasSavedSemrushKey ? 'Clés principales configurées' : 'Configuration à compléter' }}
            </span>
        </section>

        <section class="panel settings-panel">
            <div class="settings-panel-head">
                <span>01</span>
                <div>
                    <h2>Génération & données SEO</h2>
                    <p>Ajoutez les clés nécessaires à la rédaction automatique et aux imports de mots-clés.</p>
                </div>
            </div>

            <div class="settings-field-grid">
                <div class="field">
                    <label>Clé API IA</label>
                    <div class="secret-input">
                        <input wire:model="apiKey" type="password" autocomplete="new-password" placeholder="{{ $hasSavedKey ? 'Laisser vide pour conserver la clé existante' : 'Coller la clé API de génération' }}">
                        <span>secret</span>
                    </div>
                    @error('apiKey')<small class="field-error">{{ $message }}</small>@enderror
                    <p class="field-help">Chiffrée en base avec APP_KEY. Elle n'est jamais renvoyée au navigateur après enregistrement.</p>
                </div>

                <div class="field">
                    <label>Clé API Semrush</label>
                    <div class="secret-input">
                        <input wire:model="semrushApiKey" type="password" autocomplete="new-password" placeholder="{{ $hasSavedSemrushKey ? 'Laisser vide pour conserver la clé existante' : 'Coller la clé API Semrush' }}">
                        <span>secret</span>
                    </div>
                    @error('semrushApiKey')<small class="field-error">{{ $message }}</small>@enderror
                    <p class="field-help">Utilisée pour analyser les keyword seeds et enrichir la Content Factory.</p>
                </div>

                <div class="field settings-wide-field">
                    <label>Modèle de génération</label>
                    <select wire:model="model">
                        <option value="gemini-2.5-flash-lite">Flash-Lite - économique par défaut</option>
                        <option value="gemini-2.5-flash">Flash - secours manuel plus robuste</option>
                    </select>
                    <p class="field-help">Le nom technique reste stocké côté serveur, mais l'interface expose seulement le niveau de génération.</p>
                </div>
            </div>
        </section>

        <section class="panel settings-panel settings-indexing-panel">
            <div class="settings-panel-head">
                <span>02</span>
                <div>
                    <h2>Indexation automatique</h2>
                    <p>Publier un article peut déclencher IndexNow, soumettre le sitemap et enregistrer l'état connu par Search Console.</p>
                </div>
            </div>

            <label class="settings-toggle-row">
                <input wire:model="indexingAutoEnabled" type="checkbox">
                <span>
                    <b>Auto-indexation après publication</b>
                    <small>Les appels partent dans un worker : aucune publication ne doit attendre Google ou Bing.</small>
                </span>
            </label>
        </section>

        <div class="settings-provider-grid">
            <section class="panel settings-provider-card">
                <header>
                    <div>
                        <span class="settings-kicker">Bing</span>
                        <h2>IndexNow</h2>
                    </div>
                    <span class="{{ $indexingSummary['indexnow_enabled'] ? 'connection-state connected':'connection-state' }}">
                        <i></i>{{ $indexingSummary['indexnow_enabled'] ? 'Actif' : 'Inactif' }}
                    </span>
                </header>

                <label class="settings-switch">
                    <input wire:model="indexNowEnabled" type="checkbox">
                    <span></span>
                    <b>Activer IndexNow</b>
                </label>

                <div class="field">
                    <label>Clé IndexNow</label>
                    <div class="secret-input">
                        <input wire:model="indexNowKey" autocomplete="off" placeholder="Générer ou coller une clé">
                        <span>key</span>
                    </div>
                    @error('indexNowKey')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <button class="secondary-button" type="button" wire:click="generateIndexNowKey">Générer la clé</button>

                @if($indexingSummary['indexnow_key_location'])
                    <p class="field-help">Fichier de vérification : <a href="{{ $indexingSummary['indexnow_key_location'] }}" target="_blank" rel="noopener">{{ $indexingSummary['indexnow_key_location'] }}</a></p>
                @endif
            </section>

            <section class="panel settings-provider-card">
                <header>
                    <div>
                        <span class="settings-kicker">Google</span>
                        <h2>Search Console</h2>
                    </div>
                    <span class="{{ $indexingSummary['google_enabled'] ? 'connection-state connected':'connection-state' }}">
                        <i></i>{{ $indexingSummary['google_enabled'] ? 'Prêt' : 'À configurer' }}
                    </span>
                </header>

                <label class="settings-switch">
                    <input wire:model="googleSearchConsoleEnabled" type="checkbox">
                    <span></span>
                    <b>Activer Search Console</b>
                </label>

                <div class="field">
                    <label>Propriété Search Console</label>
                    <input wire:model="googleSearchConsoleSiteUrl" placeholder="https://example.com/ ou sc-domain:example.com">
                    @error('googleSearchConsoleSiteUrl')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="field">
                    <label>JSON compte de service Google {{ $hasSavedGoogleServiceAccount ? '(déjà configuré)' : '' }}</label>
                    <textarea wire:model="googleServiceAccountJson" rows="6" placeholder="{{ $hasSavedGoogleServiceAccount ? 'Laisser vide pour conserver le JSON actuel' : '{&quot;type&quot;:&quot;service_account&quot;,...}' }}"></textarea>
                    @error('googleServiceAccountJson')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <label class="settings-toggle-row compact">
                    <input wire:model="googleUrlInspectionEnabled" type="checkbox">
                    <span>
                        <b>Inspecter les URLs publiées</b>
                        <small>Google remonte l'état connu dans Search Console, sans garantir l'indexation.</small>
                    </span>
                </label>
            </section>

            <section class="panel settings-provider-card">
                <header>
                    <div>
                        <span class="settings-kicker">Bing</span>
                        <h2>Webmaster Performance</h2>
                    </div>
                    <span class="{{ $searchPerformanceSummary['bing_performance_enabled'] ? 'connection-state connected':'connection-state' }}">
                        <i></i>{{ $searchPerformanceSummary['bing_performance_enabled'] ? 'Connecté' : 'À configurer' }}
                    </span>
                </header>

                <label class="settings-switch">
                    <input wire:model="bingWebmasterEnabled" type="checkbox">
                    <span></span>
                    <b>Importer les requêtes Bing</b>
                </label>

                <div class="field">
                    <label>Site Bing Webmaster</label>
                    <input wire:model="bingWebmasterSiteUrl" placeholder="https://example.com">
                    @error('bingWebmasterSiteUrl')<small class="field-error">{{ $message }}</small>@enderror
                    <p class="field-help">Utilisez exactement l’URL déclarée dans Bing Webmaster Tools.</p>
                </div>

                <div class="field">
                    <label>Clé API Bing {{ $hasSavedBingWebmasterApiKey ? '(déjà configurée)' : '' }}</label>
                    <div class="secret-input">
                        <input wire:model="bingWebmasterApiKey" type="password" autocomplete="new-password" placeholder="{{ $hasSavedBingWebmasterApiKey ? 'Laisser vide pour conserver la clé existante' : 'Coller la clé API Bing Webmaster' }}">
                        <span>secret</span>
                    </div>
                    @error('bingWebmasterApiKey')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <p class="field-help">Ces données alimentent SEO Intelligence : gaps, refreshs, CTR et briefs anti-doublon.</p>
            </section>
        </div>

        <div class="settings-action-bar">
            <button class="secondary-button" type="button" wire:click="test">
                <span wire:loading.remove wire:target="test">Tester la connexion IA</span>
                <span wire:loading wire:target="test">Test...</span>
            </button>
            <button class="secondary-button" type="button" wire:click="submitSitemap">
                <span wire:loading.remove wire:target="submitSitemap">Soumettre le sitemap</span>
                <span wire:loading wire:target="submitSitemap">Envoi...</span>
            </button>
            <button class="primary-button" type="submit">Enregistrer les réglages</button>
        </div>
    </form>

    @if($recentIndexingSubmissions->isNotEmpty())
        <section class="panel settings-panel settings-log-panel">
            <div class="panel-head">
                <div>
                    <h2>Dernières soumissions</h2>
                    <p>Search Console, inspection URL et IndexNow.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Moteur</th>
                            <th>Type</th>
                            <th>URL</th>
                            <th>Statut</th>
                            <th>HTTP</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentIndexingSubmissions as $submission)
                            <tr>
                                <td><span class="role-badge">{{ str_replace('_', ' ', $submission->provider) }}</span></td>
                                <td>{{ $submission->type }}</td>
                                <td>
                                    <span class="indexing-url" title="{{ $submission->url }}">{{ Str::limit($submission->url, 72) }}</span>
                                    @if($submission->error_message)<small>{{ Str::limit($submission->error_message, 110) }}</small>@endif
                                </td>
                                <td><span class="state-badge {{ $submission->status }}">{{ $submission->status }}</span></td>
                                <td>{{ $submission->http_status ?: '-' }}</td>
                                <td>{{ $submission->submitted_at?->diffForHumans() ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="panel settings-panel">
        <div class="settings-panel-head">
            <span>04</span>
            <div>
                <h2>Synchronisation Dev → Prod</h2>
                <p>Exportez votre configuration (projets, mots-clés, sources) depuis le Dev et importez-la en Prod. Les articles générés ne sont pas inclus.</p>
            </div>
        </div>

        @if($syncMessage)
            <div class="alert success">{{ $syncMessage }}</div>
        @endif

        <div class="settings-field-grid">
            <div class="field">
                <label>Exporter la configuration</label>
                <button type="button" wire:click="exportConfig" wire:loading.attr="disabled" class="primary-button" style="width: auto; padding: 0 20px; height: 42px; font-size: 13px;">
                    <span wire:loading.remove wire:target="exportConfig">↓ Télécharger config.json</span>
                    <span wire:loading wire:target="exportConfig">Création de l'archive…</span>
                </button>
                <p class="field-help">Génère un fichier JSON contenant tous vos projets, mots-clés, clusters et sources vérifiées.</p>
            </div>

            <div class="field">
                <label>Importer une configuration</label>
                <label class="sync-file-label">
                    <svg viewBox="0 0 24 24" style="width:15px;fill:currentColor;flex-shrink:0"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>
                    <span>Choisir un fichier .json</span>
                    <input type="file" accept=".json" style="display:none" onchange="settingsSyncUpload(this)">
                </label>
                <p class="field-help">Remplace la configuration actuelle par celle du fichier importé. Irréversible sans backup.</p>
            </div>
        </div>

        @if($syncIsImporting)
            <div class="sync-progress-wrap">
                <div class="sync-progress-header">
                    <span>Importation en cours — ne fermez pas la page</span>
                    <strong>{{ $syncImportProgress }}%</strong>
                </div>
                <div class="sync-progress-track">
                    <span style="width:{{ $syncImportProgress }}%"></span>
                </div>
            </div>
        @endif
    </section>

    <script>
    async function settingsSyncUpload(input) {
        const file = input.files[0];
        if (!file) return;
        const chunkSize = 512 * 1024;
        const totalChunks = Math.ceil(file.size / chunkSize);
        const uploadId = Date.now().toString();
        @this.set('syncIsImporting', true);
        @this.set('syncImportProgress', 0);
        for (let i = 0; i < totalChunks; i++) {
            const start = i * chunkSize;
            const end = Math.min(start + chunkSize, file.size);
            const text = await file.slice(start, end).text();
            const isLast = (i === totalChunks - 1);
            await @this.receiveConfigChunk(uploadId, text, isLast);
            @this.set('syncImportProgress', Math.round(((i + 1) / totalChunks) * 100));
        }
        input.value = '';
    }
    </script>

    <section class="panel settings-guidance">
        <div>
            <span class="settings-kicker">Bonnes pratiques</span>
            <h2>Garder le pilotage sous contrôle</h2>
        </div>
        <ul>
            <li>Gardez des alertes de facturation sur les APIs de génération et Semrush.</li>
            <li>La boucle Semrush planifiée reste désactivée tant que SEMRUSH_SEED_EXPANSION_ENABLED=false.</li>
            <li>Search Console accepte la soumission de sitemap. L'Indexing API directe de Google est réservée aux JobPosting et BroadcastEvent.</li>
            <li>En production, privilégiez un gestionnaire de secrets externe.</li>
        </ul>
        <a href="https://ai.google.dev/gemini-api/docs/api-key" target="_blank" rel="noopener">Documentation API</a>
    </section>
</div>

