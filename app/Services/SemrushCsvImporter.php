<?php

namespace App\Services;

use App\Models\Keyword;
use App\Models\SeoProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SemrushCsvImporter
{
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
            fclose($handle);
            throw new RuntimeException('Colonne de mot-clé introuvable. Copiez aussi la ligne d’en-tête Semrush (« Mot clé », « Intention », « Volume », « KD % »).');
        }

        $count = 0;
        DB::transaction(function () use ($project, $handle, $headers, $delimiter, &$count): void {
            while (($row = fgetcsv($handle, separator: $delimiter)) !== false) {
                $row = array_pad($row, count($headers), null);
                $data = array_combine($headers, array_slice($row, 0, count($headers)));
                $keyword = $this->cleanKeyword((string) ($data['keyword'] ?? ''));
                if (! $this->isValidKeyword($keyword)) {
                    continue;
                }
                $volume = $this->number($data['search_volume'] ?? 0);
                $difficulty = $this->nullableNumber($data['keyword_difficulty'] ?? null)
                    ?? $this->competitionDifficulty($data['competition'] ?? null)
                    ?? 0.0;
                $intent = $this->normalizeIntent((string) ($data['intent'] ?? '')) ?: $this->intent($keyword, $project->name);
                Keyword::query()->updateOrCreate(
                    ['seo_project_id' => $project->id, 'keyword' => $keyword],
                    [
                        'search_volume' => (int) $volume,
                        'keyword_difficulty' => $difficulty,
                        'intent' => $intent,
                        'cpc' => $this->nullableNumber($data['cpc'] ?? $data['cpc_low'] ?? null),
                        'trend' => $data['trend'] ?? null,
                        'country' => strtoupper((string) ($data['country'] ?? $project->country)),
                        'serp_features' => $data['serp_features'] ?? null,
                        'current_position' => $this->nullableNumber($data['current_position'] ?? null),
                        'ranking_url' => $data['ranking_url'] ?? null,
                        'cluster' => $this->cluster($keyword, $intent),
                        'opportunity_score' => $this->score($volume, $difficulty, $intent, $keyword, $project->name),
                    ],
                );
                $count++;
            }
        });
        fclose($handle);

        return $count;
    }

    private function score(float $volume, float $difficulty, string $intent, string $keyword, string $brand): float
    {
        $intentWeight = match (true) {
            preg_match('/transaction|commercial|achat|buy/iu', $intent) === 1 => 2.0,
            preg_match('/navigation|brand|marque/iu', $intent) === 1 => 1.7,
            default => 1.0,
        };
        $affiliate = preg_match('/avis|prix|tarif|vs|alternative|meilleur|promo|essai/iu', $keyword) ? 1.8 : 1.0;
        $relevance = stripos($keyword, $brand) !== false ? 1.5 : 1.0;
        $raw = ($volume + 10) * $intentWeight * $affiliate * $relevance / max($difficulty, 10);

        return round(min(100, log10(max($raw, 1)) * 32), 2);
    }

    private function cluster(string $keyword, string $intent): string
    {
        return match (true) {
            preg_match('/\bvs\b|comparatif|comparaison/iu', $keyword) === 1 => 'Comparaisons',
            preg_match('/alternative|concurrent|comme/iu', $keyword) === 1 => 'Alternatives',
            preg_match('/prix|tarif|coût|promo/iu', $keyword) === 1 => 'Tarifs',
            preg_match('/avis|test|review/iu', $keyword) === 1 => 'Avis',
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
        $scores = ["\t" => 0, ';' => 0, ',' => 0];
        foreach ($lines as $line) {
            foreach (array_keys($scores) as $candidate) {
                $scores[$candidate] = max($scores[$candidate], substr_count($line, $candidate));
            }
        }

        arsort($scores);

        return (string) array_key_first($scores);
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
        if (! $headerWindow->contains('intention')
            || ! $headerWindow->contains('volume')
            || ! $headerWindow->contains(fn (string $line) => $line === 'kd' || str_starts_with($line, 'kd '))) {
            return $contents;
        }

        $dataStart = $lines->search(fn (string $line) => str_starts_with($this->normalizedLabel($line), 'selectionnes'));
        if ($dataStart === false) {
            $updatedAt = $lines->search(fn (string $line) => $this->normalizedLabel($line) === 'mise a jour');
            $dataStart = $updatedAt === false ? (int) $headerIndex + $headerWindow->count() : (int) $updatedAt;
        }
        $tokens = $lines->slice((int) $dataStart + 1)->values()->all();
        $groups = [];
        for ($index = 0; $index < count($tokens);) {
            if (! $this->looksLikeKeywordToken($tokens[$index])) {
                $index++;

                continue;
            }
            $keyword = $tokens[$index++];
            $metrics = [];
            while ($index < count($tokens) && ! $this->looksLikeKeywordToken($tokens[$index])) {
                $metrics[] = $tokens[$index++];
            }
            $groups[] = [$keyword, $metrics];
        }

        $rows = [];
        foreach ($groups as [$keyword, $metrics]) {
            $intent = [];
            while ($metrics !== [] && preg_match('/^[ICTN](?:\s*[+,\/]?\s*[ICTN])*$/iu', $metrics[0]) === 1) {
                $intent[] = array_shift($metrics);
            }
            $numeric = array_values(array_filter($metrics, fn (string $value) => $this->looksLikeNumericMetric($value)));
            if ($numeric === []) {
                continue;
            }
            $volume = array_shift($numeric);
            while ($numeric !== [] && str_contains($numeric[0], '%')) {
                array_shift($numeric);
            }
            $difficulty = array_shift($numeric);
            if ($difficulty === null || $this->number($difficulty) < 0 || $this->number($difficulty) > 100) {
                continue;
            }
            $cpc = $numeric[0] ?? '';
            $rows[] = [$keyword, implode(' ', $intent), $volume, $difficulty, $cpc];
        }

        if (count($rows) < 2) {
            throw new RuntimeException('Le tableau Semrush collé a perdu ses colonnes. Copiez la ligne d’en-tête et les lignes complètes du tableau.');
        }

        return collect([['keyword', 'intent', 'search_volume', 'keyword_difficulty', 'cpc'], ...$rows])
            ->map(fn (array $row) => implode("\t", $row))
            ->implode("\n");
    }

    private function looksLikeKeywordToken(string $value): bool
    {
        $label = $this->normalizedLabel($value);
        if ($label === ''
            || preg_match('/^[ICTN](?:\s*[+,\/]?\s*[ICTN])*$/iu', $value) === 1
            || preg_match('/^[+\-]?\d[\d\s.,]*(?:[KMB])?%?$/iu', $value) === 1
            || preg_match('/^\d+\s+(?:jours?|semaines?|mois|ans?)$/iu', $value) === 1
            || preg_match('/^(?:selectionnes|mise a jour|intention|volume|tendance|kd(?: |$)|cpc(?: |$)|con|fs|resultats)$/u', $label) === 1) {
            return false;
        }

        return preg_match('/\p{L}{2}/u', $value) === 1;
    }

    private function looksLikeNumericMetric(string $value): bool
    {
        return preg_match('/^[+\-]?\d[\d\s\x{202F}\x{00A0}.,]*%?$/u', $value) === 1;
    }

    private function isValidKeyword(string $value): bool
    {
        return $this->looksLikeKeywordToken($value);
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
