<?php

namespace App\Services;

use App\Models\ContentClaim;
use App\Models\EvidenceChunk;
use App\Models\Plan;
use App\Models\SeoProject;
use App\Models\SourcePage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ContentClaimService
{
    /** @return Collection<int, ContentClaim> */
    public function syncProject(SeoProject $project): Collection
    {
        $project->loadMissing([
            'sourcePages.evidenceChunks',
            'pricingPlans' => fn ($query) => $query->where('is_active', true),
        ]);

        $claims = collect();
        foreach ($project->sourcePages as $source) {
            if ($source->status !== 'verified') {
                continue;
            }
            foreach ($source->evidenceChunks as $chunk) {
                $claims->push($this->syncEvidenceClaim($project, $source, $chunk));
            }
        }

        foreach ($project->pricingPlans as $plan) {
            $claims = $claims->merge($this->syncPlanClaims($project, $plan));
        }

        return $claims->filter()->values();
    }

    /** @return Collection<int, ContentClaim> */
    public function claimsForArticle(int $articleId): Collection
    {
        return ContentClaim::query()
            ->whereHas('sourcePage.articles', fn ($query) => $query->whereKey($articleId))
            ->orWhereHas('plan.project.articles', fn ($query) => $query->whereKey($articleId))
            ->get();
    }

    private function syncEvidenceClaim(SeoProject $project, SourcePage $source, EvidenceChunk $chunk): ?ContentClaim
    {
        $claim = trim((string) ($chunk->value ?: $chunk->source_excerpt));
        if ($claim === '') {
            return null;
        }

        $type = $this->claimType($chunk->category.' '.$claim.' '.$source->type);
        $subject = $source->competitor_name ?: $project->name;
        $confidence = max((float) $chunk->confidence_score, (float) $source->confidence_score);

        return ContentClaim::query()->updateOrCreate(
            [
                'seo_project_id' => $project->id,
                'claim_hash' => $this->hash($source->id, $type, $subject, $claim),
            ],
            [
                'source_page_id' => $source->id,
                'plan_id' => null,
                'claim_type' => $type,
                'subject' => $subject,
                'claim' => mb_substr($claim, 0, 2000),
                'value' => mb_substr((string) $chunk->source_excerpt, 0, 2000),
                'status' => $confidence >= 0.70 ? 'verified' : 'needs_review',
                'confidence_score' => round(min(1, max(0.1, $confidence)), 2),
                'usable_for' => $this->usableFor($type),
                'metadata' => ['category' => $chunk->category, 'source_type' => $source->type],
                'verified_at' => $chunk->verified_at ?: $source->verified_at,
                'next_refresh_at' => $this->nextRefreshAt($type, $chunk->verified_at ?: $source->verified_at),
            ],
        );
    }

    /** @return Collection<int, ContentClaim> */
    private function syncPlanClaims(SeoProject $project, Plan $plan): Collection
    {
        $subject = $plan->competitor_name ?: $project->name;
        $verifiedAt = $plan->verified_at ?: now();
        $claims = collect();

        $priceClaim = trim($subject.' - offre '.$plan->name.' : '.$plan->publicPriceLabel());
        $claims->push($this->syncPlanClaim($project, $plan, 'price', $subject, $priceClaim, $plan->raw_price, $verifiedAt));

        if ($plan->priceMinimum() === 0.0) {
            $claims->push($this->syncPlanClaim($project, $plan, 'free_plan', $subject, "{$subject} propose une offre gratuite ({$plan->name}).", $plan->publicPriceLabel(), $verifiedAt));
        }

        if ($plan->free_trial_days) {
            $claims->push($this->syncPlanClaim($project, $plan, 'trial', $subject, "{$subject} mentionne un essai gratuit de {$plan->free_trial_days} jours pour {$plan->name}.", (string) $plan->free_trial_days, $verifiedAt));
        }

        foreach (collect($plan->features ?? [])->take(8) as $feature) {
            $feature = trim((string) $feature);
            if ($feature !== '') {
                $claims->push($this->syncPlanClaim($project, $plan, 'feature', $subject, "{$subject} - {$plan->name} : {$feature}.", $feature, $verifiedAt));
            }
        }

        return $claims->filter()->values();
    }

    private function syncPlanClaim(SeoProject $project, Plan $plan, string $type, string $subject, string $claim, ?string $value, $verifiedAt): ContentClaim
    {
        return ContentClaim::query()->updateOrCreate(
            [
                'seo_project_id' => $project->id,
                'claim_hash' => $this->hash($plan->source_page_id ?: 'plan', $type, $subject, $claim),
            ],
            [
                'source_page_id' => $plan->source_page_id,
                'plan_id' => $plan->id,
                'claim_type' => $type,
                'subject' => $subject,
                'claim' => mb_substr($claim, 0, 2000),
                'value' => $value ? mb_substr($value, 0, 2000) : null,
                'status' => 'verified',
                'confidence_score' => round(min(1, max(0.1, (float) $plan->confidence_score)), 2),
                'usable_for' => $this->usableFor($type),
                'metadata' => ['plan' => $plan->name, 'competitor_name' => $plan->competitor_name],
                'verified_at' => $verifiedAt,
                'next_refresh_at' => $this->nextRefreshAt($type, $verifiedAt),
            ],
        );
    }

    private function claimType(string $value): string
    {
        $normalized = Str::ascii(mb_strtolower($value));

        return match (true) {
            preg_match('/\b(?:prix|tarif|abonnement|offre|formule|euro|eur|gratuit|essai)\b/u', $normalized) === 1 => 'price',
            preg_match('/\b(?:limite|restriction|plafond|condition|reserve|inconvenient)\b/u', $normalized) === 1 => 'limitation',
            preg_match('/\b(?:obligatoire|legal|loi|dgfip|impots|urssaf|tva|facturation electronique)\b/u', $normalized) === 1 => 'legal',
            default => 'feature',
        };
    }

    /** @return string[] */
    private function usableFor(string $type): array
    {
        return match ($type) {
            'price', 'free_plan', 'trial' => ['pricing', 'comparison', 'alternatives', 'best_tools', 'tool_review'],
            'legal' => ['informational', 'pricing', 'comparison'],
            'limitation' => ['comparison', 'alternatives', 'best_tools', 'tool_review'],
            default => ['informational', 'tool_review', 'comparison', 'alternatives', 'best_tools'],
        };
    }

    private function nextRefreshAt(string $type, $verifiedAt)
    {
        $base = $verifiedAt ? Carbon::parse($verifiedAt) : now();

        return match ($type) {
            'price', 'free_plan', 'trial', 'legal' => $base->copy()->addDays(14),
            'limitation' => $base->copy()->addDays(30),
            default => $base->copy()->addDays(45),
        };
    }

    private function hash(int|string|null $source, string $type, string $subject, string $claim): string
    {
        $normalized = Str::ascii(mb_strtolower($source.'|'.$type.'|'.$subject.'|'.$claim));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?: $normalized;

        return sha1(trim($normalized));
    }
}
