<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'usage_limits' => 'array',
        'features' => 'array',
        'price_variants' => 'array',
        'tax_included' => 'boolean',
        'promotional_price' => 'boolean',
        'verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function sourcePage(): BelongsTo
    {
        return $this->belongsTo(SourcePage::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class);
    }

    public function priceMinimum(): ?float
    {
        $value = $this->monthly_price ?? $this->annual_effective_monthly ?? $this->annual_total;

        return $value !== null ? (float) $value : null;
    }

    public function priceMaximum(): ?float
    {
        $value = $this->monthly_price !== null
            ? ($this->monthly_price_max ?? $this->monthly_price)
            : ($this->annual_effective_monthly !== null
                ? ($this->annual_effective_monthly_max ?? $this->annual_effective_monthly)
                : ($this->annual_total_max ?? $this->annual_total));

        return $value !== null ? (float) $value : null;
    }

    public function formattedPriceRange(): string
    {
        $minimum = $this->priceMinimum();
        $maximum = $this->priceMaximum();
        if ($minimum === null) {
            return str_contains(mb_strtolower((string) $this->raw_price), 'prix sur demande')
                ? 'Prix sur demande'
                : 'Voir les conditions officielles';
        }

        $format = static fn (float $amount): string => rtrim(rtrim(number_format($amount, 2, ',', ' '), '0'), ',');
        $currency = match ($this->currency) {
            'EUR' => '€',
            'GBP' => '£',
            default => $this->currency ?: '',
        };

        return $maximum !== null && $minimum !== $maximum
            ? 'De '.trim($format($minimum).' '.$currency).' à '.trim($format($maximum).' '.$currency)
            : trim($format($minimum).' '.$currency);
    }

    public function displayPeriod(): string
    {
        if ($this->monthly_price !== null || $this->annual_effective_monthly !== null) {
            return '/ mois';
        }

        return $this->annual_total !== null ? '/ an' : '';
    }

    public function publicPriceLabel(): string
    {
        $price = $this->formattedPriceRange();
        $minimum = $this->priceMinimum();
        $maximum = $this->priceMaximum();
        $startsAt = $minimum !== null
            && $minimum > 0
            && $minimum === $maximum
            && collect($this->price_variants ?? [])->contains(
                fn ($variant): bool => is_array($variant)
                    && str_starts_with(mb_strtolower(trim((string) ($variant['price'] ?? ''))), 'dès '),
            );

        return trim(($startsAt ? 'Dès ' : '').$price.' '.$this->displayPeriod());
    }

    public function publicFeatureSummary(): ?string
    {
        $features = collect($this->features ?? [])
            ->filter(fn ($feature): bool => is_string($feature) && trim($feature) !== '')
            ->map(fn (string $feature): string => trim($feature, " \t\n\r\0\x0B.;"))
            ->reject(fn (string $feature): bool => str_starts_with(mb_strtolower($feature), 'toutes les fonctionnalités'))
            ->unique()
            ->take(2)
            ->values();

        if ($features->isEmpty()) {
            return null;
        }

        return mb_substr($features->implode(' · '), 0, 180);
    }
}
