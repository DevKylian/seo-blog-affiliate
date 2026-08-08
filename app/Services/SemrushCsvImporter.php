<?php

namespace App\Services;

use App\Models\Keyword;
use App\Models\SeoProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SemrushCsvImporter
{
    public function __construct(
        private readonly AffiliateIntentClassifier $affiliateIntent,
        private readonly CompetitorCatalog $competitors
    ) {}

    public function import(SeoProject $project, string $path): int
    {
        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            throw new RuntimeException('Le fichier CSV est vide ou illisible.');
        }

        return $this->importText($project, $contents);
    }

    public function importText(SeoProject $project, string $contents): int
    {
        if (trim($contents) === '') {
            throw new RuntimeException('Le texte de mots-clés est vide.');
        }

        $contents = $this->toUtf8($contents);
        $contents = $this->normalizeFlattenedSemrushClipboard($contents);
        $delimiter = $this->delimiter($contents);
        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            throw new RuntimeException('Impossible de lire le fichier CSV.');
        }
        fwrite($handle, $contents);
        rewind($handle);

        $headers = null;
        for ($line = 0; $line < 20 && ($candidate = fgetcsv($handle, separator: $delimiter)) !== false; $line++) {
            $normalized = $this->headers($candidate);
            if (in_array('keyword', $normalized, true)) {
                $headers = $normalized;
                break;
            }
        }

        if ($headers === null) {
            rewind($handle);
            $count = 0;
            DB::transaction(function () use ($project, $handle, $delimiter, &$count): void {
                while (($row = fgetcsv($handle, separator: $delimiter)) !== false) {
                    $rawText = trim((string) ($row[0] ?? ''));
                    $rawText = preg_replace('/^[0-9]+[\.\)\-]\s*|^[\-\*\•]\s*/u', '', $rawText);
                    $keyword = $this->cleanKeyword($rawText);
                    if (! $this->isValidKeyword($keyword, $project)) {
                        continue;
                    }
                    $data = [
                        'keyword' => $keyword,
                        'volume' => isset($row[1]) && is_numeric(trim($row[1])) ? (float) trim($row[1]) : 100,
                        'kd' => isset($row[2]) && is_numeric(trim($row[2])) ? (float) trim($row[2]) : 20,
                        'intent' => preg_match('/\b(?:comment|pourquoi|quand|quel|quelle|quels|quelles|combien|est-ce|ou|où)\b|\?/iu', $keyword) === 1 ? 'Informationnelle' : 'Informationnelle',
                    ];
                    $this->upsertKeywordFromData($project, $keyword, $data);
                    $count++;
                }
            });
            fclose($handle);

            if ($count > 0) {
                $this->backfillEquivalentMetrics($project);
                app(SemanticKeywordClusterer::class)->rebuildProject($project);

                return $count;
            }

            throw new RuntimeException('Aucune donnée valide trouvée. Copiez une liste de questions ou l’en-tête Semrush (« Mot clé », « Intention », « Volume »).');
        }

        $count = 0;
        DB::transaction(function () use ($project, $handle, $headers, $delimiter, &$count): void {
            while (($row = fgetcsv($handle, separator: $delimiter)) !== false) {
                $row = array_pad($row, count($headers), null);
                $data = array_combine($headers, array_slice($row, 0, count($headers)));
                $keyword = $this->cleanKeyword((string) ($data['keyword'] ?? ''));
                if (! $this->isValidKeyword($keyword, $project)) {
                    continue;
                }
                $this->upsertKeywordFromData($project, $keyword, $data);
                $count++;
            }
        });
        fclose($handle);

        if ($count > 0) {
            $this->backfillEquivalentMetrics($project);
            app(SemanticKeywordClusterer::class)->rebuildProject($project);
        }

        return $count;
    }

    public function importMetricRow(SeoProject $project, array $data, bool $refreshProject = true): ?Keyword
    {
        $keyword = $this->cleanKeyword((string) ($data['keyword'] ?? ''));
        if (! $this->isValidKeyword($keyword, $project)) {
            return null;
        }

        $model = DB::transaction(fn (): Keyword => $this->upsertKeywordFromData($project, $keyword, $data));

        if ($refreshProject) {
            $this->refreshProject($project);
        }

        return $model;
    }

    public function refreshProject(SeoProject $project): void
    {
        $this->backfillEquivalentMetrics($project);
        app(SemanticKeywordClusterer::class)->rebuildProject($project);
    }

    private function upsertKeywordFromData(SeoProject $project, string $keyword, array $data): Keyword
    {
        $existing = Keyword::query()->firstOrNew([
            'seo_project_id' => $project->id,
            'keyword' => $keyword,
        ]);
        $volume = $this->nullableNumber($data['search_volume'] ?? null)
            ?? (float) ($existing->exists ? $existing->search_volume : 0);
        $difficulty = $this->nullableNumber($data['keyword_difficulty'] ?? null)
            ?? $this->competitionDifficulty($data['competition'] ?? null)
            ?? ($existing->exists ? (float) $existing->keyword_difficulty : null)
            ?? 0.0;
        $intent = $this->normalizeIntent((string) ($data['intent'] ?? '')) ?: $this->intent($keyword, $project->name);
        $cpc = $this->firstNullableNumber($data['cpc'] ?? null, $data['cpc_low'] ?? null)
            ?? ($existing->exists ? $existing->cpc : null);
        $classification = $this->affiliateIntent->classify($keyword, $project->name, $intent, (float) $volume, (float) $difficulty, $cpc);
        $existing->fill([
            'search_volume' => (int) $volume,
            'keyword_difficulty' => $difficulty,
            'intent' => $intent,
            'intent_type' => $classification['intent_type'],
            'cpc' => $cpc,
            'trend' => $this->filledValue($data['trend'] ?? null, $existing->exists ? $existing->trend : null),
            'country' => strtoupper((string) $this->filledValue($data['country'] ?? null, $existing->exists ? $existing->country : $project->country)),
            'serp_features' => $this->filledValue($data['serp_features'] ?? null, $existing->exists ? $existing->serp_features : null),
            'current_position' => $this->nullableNumber($data['current_position'] ?? null) ?? ($existing->exists ? $existing->current_position : null),
            'ranking_url' => $this->filledValue($data['ranking_url'] ?? null, $existing->exists ? $existing->ranking_url : null),
            'cluster' => $this->cluster($keyword, $intent),
            'affiliate_cluster' => $classification['affiliate_cluster'],
            'affiliate_priority' => $classification['affiliate_priority'],
            'user_moment' => $classification['user_moment'],
            'problem_label' => $classification['problem_label'],
            'solution_label' => $classification['solution_label'],
            'opportunity_score' => $this->score($volume, $difficulty, $intent, $keyword, $project->name, $classification['affiliate_priority']),
        ])->save();

        return $existing;
    }

    private function score(float $volume, float $difficulty, string $intent, string $keyword, string $brand, float $affiliatePriority = 0): float
    {
        // On délègue au SemrushSmartScorer qui contient la formule du "SEO Strategist"
        // On construit un mock de Keyword pour le passer au scorer
        $mockKeyword = new Keyword([
            'keyword' => $keyword,
            'search_volume' => $volume,
            'keyword_difficulty' => $difficulty,
            'intent' => $intent
        ]);
        
        $mockProject = new SeoProject(['name' => $brand]);

        return app(SemrushSmartScorer::class)->calculateScore($mockKeyword, $mockProject);
    }

    private function cluster(string $keyword, string $intent): string
    {
        return match (true) {
            preg_match('/\bvs\b|comparatif|comparaison/iu', $keyword) === 1 => 'Comparaisons',
            preg_match('/alternative|concurrent|comme/iu', $keyword) === 1 => 'Alternatives',
            preg_match('/prix|tarif|coût|promo/iu', $keyword) === 1 => 'Tarifs',
            preg_match('/avis|test|review/iu', $keyword) === 1 => 'Avis',
            preg_match('/\b(?:comment|pourquoi|quand|quel|quelle|quels|quelles|combien|est-ce|ou|où)\b|\?/iu', $keyword) === 1 => 'Questions & Tutoriels',
            default => ucfirst(mb_strtolower($intent ?: 'Informationnel')),
        };
    }

    private function header(string $value): string
    {
        $value = Str::of(trim($value, "\xEF\xBB\xBF \t\n\r\0\x0B"))
            ->ascii()
            ->lower()
            ->replace("\u{00A0}", ' ')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();

        $aliases = [
            'keyword' => 'keyword',
            'keywords' => 'keyword',
            'mot cle' => 'keyword',
            'mots cles' => 'keyword',
            'search volume' => 'search_volume',
            'volume' => 'search_volume',
            'avg monthly searches' => 'search_volume',
            'average monthly searches' => 'search_volume',
            'recherches mensuelles moyennes' => 'search_volume',
            'difficulty' => 'keyword_difficulty',
            'difficulte' => 'keyword_difficulty',
            'difficulte mot cle' => 'keyword_difficulty',
            'difficulte du mot cle' => 'keyword_difficulty',
            'keyword difficulty' => 'keyword_difficulty',
            'kd' => 'keyword_difficulty',
            'competition indexed value' => 'keyword_difficulty',
            'competition index' => 'keyword_difficulty',
            'indice de concurrence' => 'keyword_difficulty',
            'competition' => 'competition',
            'concurrence' => 'competition',
            'intent' => 'intent',
            'intention' => 'intent',
            'currency' => 'currency',
            'devise' => 'currency',
            'cpc' => 'cpc',
            'cpc eur' => 'cpc',
            'cpc usd' => 'cpc',
            'top of page bid high range' => 'cpc',
            'enchere de haut de page fourchette haute' => 'cpc',
            'top of page bid low range' => 'cpc_low',
            'enchere de haut de page fourchette basse' => 'cpc_low',
            'variation sur trois mois' => 'trend',
            'three month change' => 'trend',
            'trend' => 'trend',
            'country' => 'country',
            'pays' => 'country',
            'serp features' => 'serp_features',
            'organic average position' => 'current_position',
            'position organique moyenne' => 'current_position',
            'current position' => 'current_position',
            'position' => 'current_position',
            'ranking url' => 'ranking_url',
            'url' => 'ranking_url',
        ];

        return $aliases[$value] ?? str_replace(' ', '_', $value);
    }

    private function number(mixed $value): float
    {
        $value = str_replace(["\u{00A0}", ' ', '%'], '', trim((string) $value));
        $comma = strrpos($value, ',');
        $dot = strrpos($value, '.');

        if ($comma !== false && $dot !== false) {
            $decimal = $comma > $dot ? ',' : '.';
            $thousands = $decimal === ',' ? '.' : ',';
            $value = str_replace($thousands, '', $value);
            $value = str_replace($decimal, '.', $value);
        } elseif ($comma !== false) {
            $value = preg_match('/,\d{3}$/', $value) ? str_replace(',', '', $value) : str_replace(',', '.', $value);
        } elseif ($dot !== false && preg_match('/\.\d{3}$/', $value)) {
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function nullableNumber(mixed $value): ?float
    {
        return $value === null || trim((string) $value) === '' ? null : $this->number($value);
    }

    private function firstNullableNumber(mixed ...$values): ?float
    {
        foreach ($values as $value) {
            $number = $this->nullableNumber($value);
            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    private function filledValue(mixed $candidate, mixed $fallback): mixed
    {
        return $candidate === null || trim((string) $candidate) === '' ? $fallback : $candidate;
    }

    private function backfillEquivalentMetrics(SeoProject $project): void
    {
        $keywords = $project->keywords()->get();
        $measuredByKey = $keywords
            ->filter(fn (Keyword $keyword): bool => $this->hasMetrics($keyword))
            ->groupBy(fn (Keyword $keyword): string => $this->metricEquivalenceKey($keyword->keyword))
            ->map(fn ($group): Keyword => $group->sortByDesc('search_volume')->first());

        $keywords
            ->reject(fn (Keyword $keyword): bool => $this->hasMetrics($keyword))
            ->each(function (Keyword $keyword) use ($measuredByKey, $project): void {
                $source = $measuredByKey->get($this->metricEquivalenceKey($keyword->keyword));
                if (! $source || $source->id === $keyword->id) {
                    return;
                }

                $intent = $keyword->intent ?: $source->intent ?: $this->intent($keyword->keyword, $project->name);
                $classification = $this->affiliateIntent->classify(
                    $keyword->keyword,
                    $project->name,
                    $intent,
                    (float) $source->search_volume,
                    (float) $source->keyword_difficulty,
                    $source->cpc,
                );
                $keyword->update([
                    'search_volume' => $source->search_volume,
                    'keyword_difficulty' => $source->keyword_difficulty,
                    'intent_type' => $classification['intent_type'],
                    'cpc' => $source->cpc,
                    'trend' => $keyword->trend ?: $source->trend,
                    'country' => $keyword->country ?: $source->country,
                    'serp_features' => $keyword->serp_features ?: $source->serp_features,
                    'current_position' => $keyword->current_position ?? $source->current_position,
                    'ranking_url' => $keyword->ranking_url ?: $source->ranking_url,
                    'cluster' => $this->cluster($keyword->keyword, $intent),
                    'affiliate_cluster' => $classification['affiliate_cluster'],
                    'affiliate_priority' => $classification['affiliate_priority'],
                    'user_moment' => $classification['user_moment'],
                    'problem_label' => $classification['problem_label'],
                    'solution_label' => $classification['solution_label'],
                    'opportunity_score' => $this->score((float) $source->search_volume, (float) $source->keyword_difficulty, $intent, $keyword->keyword, $project->name, $classification['affiliate_priority']),
                ]);
            });
    }

    private function hasMetrics(Keyword $keyword): bool
    {
        return (float) $keyword->keyword_difficulty > 0
            || (int) $keyword->search_volume > 0
            || $keyword->cpc !== null;
    }

    private function metricEquivalenceKey(string $keyword): string
    {
        $tokens = preg_split('/\s+/u', $this->normalizedLabel($keyword)) ?: [];
        $mapped = collect($tokens)
            ->map(fn (string $token): string => match ($token) {
                'facturation', 'facturations', 'factures' => 'facture',
                'logiciels' => 'logiciel',
                'de', 'du', 'des', 'et', 'pour', 'le', 'la', 'les', 'un', 'une' => '',
                default => $token,
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $mapped->implode('-');
    }

    private function headers(array $values): array
    {
        $seen = [];

        return array_map(function ($value, $index) use (&$seen): string {
            $header = $this->header((string) $value) ?: 'column_'.$index;
            if (isset($seen[$header])) {
                $header .= '_'.$index;
            }
            $seen[$header] = true;

            return $header;
        }, $values, array_keys($values));
    }

    private function delimiter(string $contents): string
    {
        $lines = array_slice(preg_split('/\R/u', $contents) ?: [], 0, 20);
        $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));
        if (count($lines) === 0) {
            return "\t";
        }

        $scores = ["\t" => 0, ';' => 0, ',' => 0];
        foreach (array_keys($scores) as $candidate) {
            if (substr_count($lines[0], $candidate) === 0) {
                continue;
            }
            foreach ($lines as $line) {
                $scores[$candidate] += substr_count($line, $candidate);
            }
        }

        $validScores = array_filter($scores);
        if (empty($validScores)) {
            return "\t";
        }

        arsort($validScores);

        return (string) array_key_first($validScores);
    }

    private function toUtf8(string $contents): string
    {
        if (str_starts_with($contents, "\xFF\xFE")) {
            return mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16LE');
        }
        if (str_starts_with($contents, "\xFE\xFF")) {
            return mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16BE');
        }
        if (! mb_check_encoding($contents, 'UTF-8')) {
            return mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        return $contents;
    }

    private function competitionDifficulty(mixed $competition): ?float
    {
        return match (true) {
            preg_match('/high|eleve|fort/iu', (string) $competition) === 1 => 75,
            preg_match('/medium|moyen/iu', (string) $competition) === 1 => 50,
            preg_match('/low|faible/iu', (string) $competition) === 1 => 25,
            default => null,
        };
    }

    /**
     * Le copier-coller depuis le tableau web Semrush arrive parfois avec une
     * cellule par ligne, sans tabulations. On reconstruit alors uniquement les
     * colonnes vérifiables au lieu d'enregistrer chaque nombre comme mot-clé.
     */
    private function normalizeFlattenedSemrushClipboard(string $contents): string
    {
        $lines = collect(preg_split('/\R/u', $contents) ?: [])
            ->map(fn (string $line) => $this->cleanKeyword($line))
            ->filter(fn (string $line) => $line !== '')
            ->values();
        if ($lines->count() < 8 || str_contains((string) $lines->first(), "\t")) {
            return $contents;
        }

        $headerIndex = $lines->search(fn (string $line) => in_array($this->normalizedLabel($line), ['mot cle', 'keyword'], true));
        if ($headerIndex === false) {
            return $contents;
        }
        $headerWindow = $lines->slice((int) $headerIndex, 15)->map(fn (string $line) => $this->normalizedLabel($line));
        if (! $headerWindow->contains(fn (string $line) => in_array($line, ['intention', 'intent'], true))
            || ! $headerWindow->contains('volume')
            || ! $headerWindow->contains(fn (string $line) => $line === 'kd' || str_starts_with($line, 'kd '))) {
            return $contents;
        }

        $dataStart = $lines->search(fn (string $line) => str_starts_with($this->normalizedLabel($line), 'selectionnes'));
        if ($dataStart === false) {
            $updatedAt = $lines->search(fn (string $line) => $this->normalizedLabel($line) === 'mise a jour');
            $dataStart = $updatedAt === false ? $this->flattenedHeaderEndIndex($lines, (int) $headerIndex) : (int) $updatedAt;
        }
        $rows = $this->flattenedSemrushRows($lines->slice((int) $dataStart + 1)->values()->all());

        if (count($rows) < 2) {
            throw new RuntimeException('Le tableau Semrush collé a perdu ses colonnes. Copiez la ligne d’en-tête et les lignes complètes du tableau.');
        }

        return collect([['keyword', 'intent', 'search_volume', 'keyword_difficulty', 'cpc', 'competition', 'serp_features'], ...$rows])
            ->map(fn (array $row) => implode("\t", $row))
            ->implode("\n");
    }

    /**
     * @param array<int, string> $tokens
     * @return array<int, array{0:string,1:string,2:string,3:string,4:string,5:string,6:string}>
     */
    private function flattenedSemrushRows(array $tokens): array
    {
        $rows = [];
        for ($index = 0; $index < count($tokens);) {
            $parsed = $this->flattenedSemrushRowAt($tokens, $index);
            if ($parsed === null) {
                $index++;

                continue;
            }

            $rows[] = $parsed['row'];
            $index = $parsed['next'];
        }

        return $rows;
    }

    /**
     * @param array<int, string> $tokens
     * @return array{row:array{0:string,1:string,2:string,3:string,4:string,5:string,6:string},next:int}|null
     */
    private function flattenedSemrushRowAt(array $tokens, int $start): ?array
    {
        $keyword = $tokens[$start] ?? null;
        if (! is_string($keyword)
            || ! $this->looksLikeKeywordToken($keyword)
            || $this->looksLikeSerpFeatures($keyword)
            || $this->looksLikeUpdatedMetric($keyword)) {
            return null;
        }

        $index = $start + 1;
        $intent = [];
        while (isset($tokens[$index]) && $this->looksLikeIntentToken($tokens[$index])) {
            $intent[] = $tokens[$index];
            $index++;
        }
        if ($intent === [] || ! isset($tokens[$index]) || ! $this->looksLikeNumericMetric($tokens[$index])) {
            return null;
        }

        $volume = $tokens[$index++];
        if (isset($tokens[$index], $tokens[$index + 1])
            && $this->looksLikeTrendMetric($tokens[$index])
            && $this->looksLikeDifficultyOrUnavailableMetric($tokens[$index + 1])) {
            $index++;
        }
        if (! isset($tokens[$index]) || ! $this->looksLikeDifficultyOrUnavailableMetric($tokens[$index])) {
            return null;
        }

        $difficulty = $this->looksLikeUnavailableMetric($tokens[$index]) ? '0' : $tokens[$index];
        $index++;
        if (! isset($tokens[$index]) || ! $this->looksLikeNumericMetric($tokens[$index])) {
            return null;
        }

        $cpc = $tokens[$index++];
        $competition = '';
        if (isset($tokens[$index]) && $this->looksLikeNumericMetric($tokens[$index])) {
            $competition = $tokens[$index++];
        }

        $serpFeatures = '';
        if (isset($tokens[$index]) && $this->looksLikeSerpFeatures($tokens[$index])) {
            $serpFeatures = $tokens[$index++];
        }

        if (isset($tokens[$index]) && $this->looksLikeResultsMetric($tokens[$index])) {
            $index++;
        }
        if (isset($tokens[$index]) && $this->looksLikeUpdatedMetric($tokens[$index])) {
            $index++;
        }

        if ((float) $this->number($difficulty) < 0 || (float) $this->number($difficulty) > 100) {
            return null;
        }

        return [
            'row' => [$keyword, implode(' ', $intent), $volume, $difficulty, $cpc, $competition, $serpFeatures],
            'next' => $index,
        ];
    }

    private function flattenedHeaderEndIndex($lines, int $headerIndex): int
    {
        $lastHeader = $headerIndex;
        for ($index = $headerIndex; $index < $lines->count(); $index++) {
            $line = (string) $lines[$index];
            if ($this->isFlattenedSemrushHeaderLabel($this->normalizedLabel($line))) {
                $lastHeader = $index;

                continue;
            }
            if ($index > $headerIndex && $this->looksLikeKeywordToken($line)) {
                break;
            }
        }

        return $lastHeader;
    }

    private function isFlattenedSemrushHeaderLabel(string $label): bool
    {
        return in_array($label, [
            'mot cle', 'mots cles', 'keyword', 'keywords', 'intention', 'intent',
            'volume', 'tendance', 'trend', 'kd', 'cpc', 'cpc eur', 'cpc usd',
            'com', 'con', 'concurrence', 'serp features', 'fs', 'resultats',
            'results', 'updated', 'mise a jour',
        ], true)
            || str_starts_with($label, 'kd ')
            || str_starts_with($label, 'cpc ')
            || str_starts_with($label, 'selected')
            || str_starts_with($label, 'selectionnes');
    }

    /**
     * Semrush can flatten the Trend column as "0" before the actual KD.
     *
     * @param array<int, string> $numeric
     * @return array{0:?string,1:string}
     */
    private function difficultyAndCpcFromFlattenedMetrics(array $numeric): array
    {
        if ($numeric === []) {
            return [null, ''];
        }

        if (count($numeric) >= 2
            && $this->looksLikeTrendMetric($numeric[0])
            && $this->looksLikeDifficultyMetric($numeric[1])) {
            array_shift($numeric);
        }

        return [array_shift($numeric), $numeric[0] ?? ''];
    }

    private function looksLikeTrendMetric(string $value): bool
    {
        $trimmed = trim(str_replace(["\u{00A0}", ' ', '%'], '', $value));

        return str_starts_with($trimmed, '+')
            || str_starts_with($trimmed, '-')
            || preg_match('/^0+(?:[,.]0+)?$/', $trimmed) === 1;
    }

    private function looksLikeDifficultyMetric(string $value): bool
    {
        $trimmed = trim(str_replace(["\u{00A0}", ' ', '%'], '', $value));

        return preg_match('/^\d{1,3}$/', $trimmed) === 1
            && $this->number($value) >= 0
            && $this->number($value) <= 100;
    }

    private function looksLikeUnavailableMetric(string $value): bool
    {
        return in_array($this->normalizedLabel($value), ['n a', 'na', 'non disponible'], true);
    }

    private function looksLikeDifficultyOrUnavailableMetric(string $value): bool
    {
        return $this->looksLikeDifficultyMetric($value) || $this->looksLikeUnavailableMetric($value);
    }

    private function looksLikeIntentToken(string $value): bool
    {
        return preg_match('/^[ICTN](?:\s*[+,\/]?\s*[ICTN])*$/iu', trim($value)) === 1;
    }

    private function looksLikeSerpFeatures(string $value): bool
    {
        $label = $this->normalizedLabel($value);

        return str_contains($label, 'people also ask')
            || str_contains($label, 'featured snippet')
            || str_contains($label, 'sitelinks')
            || str_contains($label, 'reviews')
            || str_contains($label, 'video')
            || str_contains($label, 'image')
            || str_contains($label, 'knowledge panel')
            || str_contains($label, 'related searches')
            || str_contains($label, 'ads top')
            || str_contains($label, 'ads middle')
            || str_contains($label, 'ads bottom');
    }

    private function looksLikeResultsMetric(string $value): bool
    {
        return preg_match('/^\d[\d\s.,]*(?:[KMB])?$/iu', trim(str_replace(["\u{00A0}", "\u{202F}"], ' ', $value))) === 1;
    }

    private function looksLikeUpdatedMetric(string $value): bool
    {
        return preg_match('/^\d+\s+(?:jours?|days?|semaines?|weeks?|mois|months?|ans?|years?)$/iu', trim($value)) === 1;
    }

    private function looksLikeKeywordToken(string $value): bool
    {
        $label = $this->normalizedLabel($value);
        if ($label === ''
            || preg_match('/^[ICTN](?:\s*[+,\/]?\s*[ICTN])*$/iu', $value) === 1
            || preg_match('/^[+\-]?\d[\d\s.,]*(?:[KMB])?%?$/iu', $value) === 1
            || preg_match('/^\d+\s+(?:jours?|semaines?|mois|ans?)$/iu', $value) === 1
            || $this->looksLikeSerpFeatures($value)
            || $this->looksLikeUpdatedMetric($value)
            || preg_match('/^(?:all keywords|total volume|average kd|selected|selectionnes|updated|mise a jour|intention|intent|volume|tendance|trend|kd(?: |$)|cpc(?: |$)|com|con|fs|serp features|resultats|results)$/u', $label) === 1) {
            return false;
        }

        return preg_match('/\p{L}{2}/u', $value) === 1;
    }

    private function looksLikeNumericMetric(string $value): bool
    {
        return preg_match('/^[+\-]?\d[\d\s\x{202F}\x{00A0}.,]*%?$/u', $value) === 1;
    }

    private function isValidKeyword(string $value, SeoProject $project): bool
    {
        if (!$this->looksLikeKeywordToken($value)) {
            return false;
        }

        // Rejeter les mots-clés qui mentionnent des marques/logiciels inventés par l'IA
        if ($this->competitors->unknownCompetitorMentions($project, $value) !== []) {
            return false;
        }

        return true;
    }

    private function cleanKeyword(string $value): string
    {
        return trim(preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value) ?? $value);
    }

    private function normalizedLabel(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    private function intent(string $keyword, string $brand): string
    {
        return match (true) {
            preg_match('/avis|prix|tarif|vs|comparatif|alternative|meilleur|promo|essai|gratuit|logiciel|outil/iu', $keyword) === 1 => 'Commerciale',
            $brand !== '' && stripos($keyword, $brand) !== false => 'Navigationnelle',
            preg_match('/acheter|abonnement|devis/iu', $keyword) === 1 => 'Transactionnelle',
            default => 'Informationnelle',
        };
    }

    private function normalizeIntent(string $intent): string
    {
        $intent = trim($intent);
        if ($intent === '' || preg_match('/^[ICTN](?:\s*[+,\/]?\s*[ICTN])*$/iu', $intent) !== 1) {
            return $intent;
        }

        $labels = ['I' => 'Informationnelle', 'C' => 'Commerciale', 'T' => 'Transactionnelle', 'N' => 'Navigationnelle'];
        preg_match_all('/[ICTN]/iu', mb_strtoupper($intent), $codes);

        return collect($codes[0] ?? [])->unique()->map(fn (string $code) => $labels[$code])->implode(', ');
    }
}
