<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class PricingComparisonPresenter
{
    /**
     * @param  Collection<string, Collection<int, \App\Models\Plan>>  $pricingGroups
     * @return Collection<int, array<string, string>>
     */
    public function rows(Collection $pricingGroups): Collection
    {
        return $pricingGroups->map(function ($plans, string $productName): array {
            $plans = collect($plans)->filter()->values();
            $entryPlan = $plans->sortBy(fn ($plan) => $plan->priceMinimum() ?? 999999999)->first();
            $freePlan = $plans->first(fn ($plan) => $plan->priceMinimum() !== null && (float) $plan->priceMinimum() === 0.0);
            $trialDays = $plans->pluck('free_trial_days')->filter()->max();

            return [
                'product' => $productName,
                'entry_price' => $entryPlan ? $entryPlan->publicPriceLabel() : 'Prix non extrait',
                'offers' => $this->offers($plans),
                'free_trial' => implode(' | ', [
                    $freePlan ? 'Gratuit : oui ('.$freePlan->name.')' : ($plans->isEmpty() ? 'Gratuit : à vérifier' : 'Gratuit : non détecté'),
                    $trialDays ? 'Essai : '.$trialDays.' jours' : 'Essai : non détecté',
                ]),
                'coverage' => $this->coverage($plans),
            ];
        })->values();
    }

    private function offers(Collection $plans): string
    {
        if ($plans->isEmpty()) {
            return 'Aucune offre exploitable extraite';
        }

        $offers = $plans
            ->take(5)
            ->map(fn ($plan): string => trim($plan->name).' : '.$plan->publicPriceLabel())
            ->implode(' | ');
        $remaining = $plans->count() - 5;

        return $remaining > 0 ? $offers.' | +'.$remaining.' autre(s)' : $offers;
    }

    private function coverage(Collection $plans): string
    {
        if ($plans->isEmpty()) {
            return 'Page source fournie, mais aucun tarif structuré n’a encore été collecté.';
        }

        $featureSummary = $plans
            ->flatMap(fn ($plan): array => is_array($plan->features ?? null) ? $plan->features : [])
            ->filter(fn ($feature): bool => is_string($feature) && trim($feature) !== '')
            ->map(fn (string $feature): string => trim($feature, " \t\n\r\0\x0B.;"))
            ->reject(fn (string $feature): bool => $this->isInheritedFeatureLabel($feature))
            ->unique(fn (string $feature): string => Str::ascii(mb_strtolower($feature)))
            ->take(6)
            ->implode(' | ');
        if ($featureSummary !== '') {
            return $featureSummary;
        }

        $contexts = $this->contexts($plans);
        if ($contexts->isNotEmpty()) {
            return $contexts->take(3)->implode(' | ');
        }

        $segments = $this->segmentsFromPlanNames($plans);
        if ($segments !== '') {
            return $segments;
        }

        return 'Tarifs extraits, périmètre fonctionnel exact à contrôler sur la source officielle.';
    }

    private function isInheritedFeatureLabel(string $feature): bool
    {
        $normalized = Str::ascii(mb_strtolower($feature));

        return str_starts_with($normalized, 'toutes les fonctionnalites')
            || preg_match('/\ben plus de l[\' ]offre\b/u', $normalized) === 1;
    }

    private function contexts(Collection $plans): Collection
    {
        return $plans->flatMap(function ($plan): array {
            $values = [];
            if (preg_match('/Public \/ objectif\s*:\s*(.+?)(?:\n|$)/u', (string) $plan->raw_price, $match) === 1) {
                $values[] = $match[1];
            }
            foreach ($plan->price_variants ?? [] as $variant) {
                if (is_array($variant)) {
                    $values[] = (string) ($variant['context'] ?? '');
                }
            }

            return $values;
        })
            ->map(fn (string $value): string => $this->cleanContext($value))
            ->filter()
            ->unique(fn (string $value): string => Str::ascii(mb_strtolower($value)))
            ->values();
    }

    private function cleanContext(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/\s+-\s+soit\s+\d+[,.]?\d*\s*€\s*\/\s*mois/iu', '', $value) ?? $value;
        $value = preg_replace('/\s+·\s+/u', ', ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value, " \t\n\r\0\x0B.;,-");
        $lower = Str::ascii(mb_strtolower($value));
        if ($value === '' || in_array($lower, ['donnees structurees de la page', 'tarif variable selon le profil, le volume ou le mode de facturation'], true)) {
            return '';
        }

        return mb_substr($value, 0, 130);
    }

    private function segmentsFromPlanNames(Collection $plans): string
    {
        $names = $plans->pluck('name')->map(fn ($name): string => trim((string) $name))->filter();
        $entities = $names
            ->flatMap(function (string $name): array {
                preg_match_all('/micro-entreprise|ei|gie|sasu\/sas|eurl\/sarl|mensuelle|trimestrielle|annuelle|module comptable|utilisateur/iu', $name, $matches);

                return $matches[0] ?? [];
            })
            ->map(fn (string $value): string => Str::headline(Str::lower($value)))
            ->unique()
            ->values();

        if ($entities->isEmpty()) {
            return '';
        }

        return 'Segmentation prix : '.$entities->take(6)->implode(', ');
    }
}
