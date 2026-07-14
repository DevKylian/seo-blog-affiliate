<?php

namespace App\Services;

use App\Models\TopicAlias;
use Illuminate\Support\Str;

final class TopicNormalizer
{
    private ?array $aliases = null;

    public function normalize(string $topic): string
    {
        $slug = Str::slug($topic) ?: 'sujet-a-preciser';
        $aliases = $this->aliases ??= TopicAlias::query()->pluck('canonical_topic', 'alias')->all();

        if (isset($aliases[$slug])) {
            return $aliases[$slug];
        }

        if (preg_match('/(?:gestion|suivi).*(?:client|clientele)|relation.*client|utilisation.*crm/u', $slug)) {
            return 'gestion-relation-client';
        }

        if (preg_match('/factur|comptab|crm-finance|devis|paiement/u', $slug)) {
            return 'crm-facturation';
        }

        return $slug;
    }

    public function key(string $value): string
    {
        return Str::slug($value) ?: 'non-precise';
    }
}
