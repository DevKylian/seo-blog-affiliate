<?php

namespace App\Services;

use App\Models\Article;
use App\Models\InternalLink;
use App\Models\Redirect;
use Illuminate\Support\Facades\DB;

final class EditorialConsolidationService
{
    public function __construct(private readonly EditorialDuplicateDetector $detector) {}

    public function merge(Article $duplicate, Article $canonical, ?float $score = null): Article
    {
        if ($duplicate->is($canonical)) {
            return $canonical;
        }

        return DB::transaction(function () use ($duplicate, $canonical, $score): Article {
            $duplicate->loadMissing(['sources', 'tools', 'categories', 'tags']);
            $canonical->loadMissing(['project', 'keyword', 'brief', 'sources', 'tools', 'categories', 'tags']);
            $duplicateWasPublished = $duplicate->status === 'published';
            $duplicatePath = $this->publicPath($duplicate);
            $canonicalOldPath = $this->publicPath($canonical);
            $blueprint = $this->detector->hydrateArticleFingerprint($canonical);

            $canonical->versions()->create([
                'user_id' => auth()->id(),
                'version' => ($canonical->versions()->max('version') ?? 0) + 1,
                'title' => $canonical->title,
                'body' => $canonical->body,
                'content_blocks' => $canonical->content_blocks,
                'change_note' => "Fusion éditoriale avec l’article #{$duplicate->id}",
            ]);

            $mergedBody = $this->mergeBodies($canonical->body, $duplicate->body);
            $blocks = collect($canonical->content_blocks ?? [])
                ->map(fn (array $block) => ($block['type'] ?? null) === 'markdown'
                    ? [...$block, 'content' => $mergedBody]
                    : $block)
                ->values()->all();
            if (! collect($blocks)->contains('type', 'markdown')) {
                array_unshift($blocks, ['type' => 'markdown', 'content' => $mergedBody]);
            }

            $canonical->update([
                ...$this->detector->articleFingerprintAttributes($blueprint),
                'title' => $this->detector->recommendedTitle($canonical->project, $blueprint),
                'slug' => $canonical->slug,
                'meta_title' => $this->detector->recommendedTitle($canonical->project, $blueprint),
                'body' => $mergedBody,
                'content_blocks' => $blocks,
                'source_ids' => collect($canonical->source_ids ?? [])->merge($duplicate->source_ids ?? [])->unique()->values()->all(),
                'status' => $duplicateWasPublished ? 'published' : $canonical->status,
                'published_at' => $duplicateWasPublished ? ($canonical->published_at ?? $duplicate->published_at ?? now()) : $canonical->published_at,
                'primary_keyword' => $blueprint['topic'] === 'utilisation-crm' ? $this->detector->displayName($canonical->project->name).' CRM' : $canonical->primary_keyword,
                'search_intent' => $blueprint['intent'],
                'canonical_article_id' => null,
                'duplicate_score' => null,
                'duplicate_status' => null,
            ]);

            $canonical->sources()->syncWithoutDetaching(
                $duplicate->sources->values()->mapWithKeys(fn ($source, $index) => [$source->id => ['citation_label' => 'S'.($index + 1)]])->all()
            );
            $canonical->tools()->syncWithoutDetaching($duplicate->tools->pluck('id')->mapWithKeys(
                fn ($id) => [$id => ['role' => (int) $id === (int) $canonical->seo_project_id ? 'featured' : 'compared']]
            )->all());
            $canonical->categories()->syncWithoutDetaching($duplicate->categories->pluck('id')->all());
            $canonical->tags()->syncWithoutDetaching($duplicate->tags->pluck('id')->all());

            $duplicate->update([
                'status' => 'archived',
                'canonical_article_id' => $canonical->id,
                'duplicate_score' => $score ?? 100,
                'duplicate_status' => 'merged',
                'published_at' => null,
                'canonical_url' => $canonical->public_url,
            ]);

            $canonicalNewPath = $this->publicPath($canonical->fresh());
            if ($duplicateWasPublished && $duplicatePath !== $canonicalNewPath) {
                Redirect::query()->updateOrCreate(
                    ['from_path' => $duplicatePath],
                    ['to_path' => $canonicalNewPath, 'status_code' => 301, 'active' => true],
                );
            }
            if ($canonicalOldPath !== $canonicalNewPath) {
                Redirect::query()->updateOrCreate(
                    ['from_path' => $canonicalOldPath],
                    ['to_path' => $canonicalNewPath, 'status_code' => 301, 'active' => true],
                );
            }

            return $canonical->fresh();
        });
    }

    public function archive(Article $article): void
    {
        $article->update(['status' => 'archived', 'duplicate_status' => 'archived']);
    }

    /**
     * Consolide une ancienne URL sous un article pilier sans réécrire ni gonfler
     * le contenu validé du pilier.
     */
    public function redirectDuplicate(Article $duplicate, Article $canonical, ?float $score = null): Article
    {
        if ($duplicate->is($canonical)) {
            return $canonical;
        }

        return DB::transaction(function () use ($duplicate, $canonical, $score): Article {
            $duplicate->loadMissing(['sources', 'tools', 'categories', 'tags']);
            $canonical->loadMissing(['sources', 'tools', 'categories', 'tags']);
            $duplicateWasPublished = $duplicate->status === 'published';
            $duplicatePath = $this->publicPath($duplicate);
            $canonicalPath = $this->publicPath($canonical);

            $canonical->update([
                'canonical_article_id' => null,
                'duplicate_score' => null,
                'duplicate_status' => null,
                'canonical_url' => null,
                'source_ids' => collect($canonical->source_ids ?? [])
                    ->merge($duplicate->source_ids ?? [])
                    ->unique()->values()->all(),
            ]);
            $canonical->sources()->syncWithoutDetaching(
                $duplicate->sources->values()->mapWithKeys(
                    fn ($source, int $index): array => [$source->id => ['citation_label' => 'S'.($index + 1)]]
                )->all()
            );
            $canonical->tools()->syncWithoutDetaching($duplicate->tools->pluck('id')->mapWithKeys(
                fn ($id): array => [$id => ['role' => (int) $id === (int) $canonical->seo_project_id ? 'featured' : 'compared']]
            )->all());
            $canonical->categories()->syncWithoutDetaching($duplicate->categories->pluck('id')->all());
            $canonical->tags()->syncWithoutDetaching($duplicate->tags->pluck('id')->all());

            $duplicate->update([
                'status' => 'archived',
                'canonical_article_id' => $canonical->id,
                'duplicate_score' => $score ?? 100,
                'duplicate_status' => 'merged',
                'published_at' => null,
                'canonical_url' => $canonical->public_url,
            ]);

            InternalLink::query()
                ->where('source_article_id', $canonical->id)
                ->where('target_article_id', $duplicate->id)
                ->delete();

            InternalLink::query()
                ->where('target_article_id', $duplicate->id)
                ->where('source_article_id', '!=', $canonical->id)
                ->get()
                ->each(function (InternalLink $link) use ($canonical): void {
                    InternalLink::query()->updateOrCreate(
                        [
                            'source_article_id' => $link->source_article_id,
                            'target_article_id' => $canonical->id,
                        ],
                        [
                            'anchor_text' => $canonical->primary_keyword ?: $canonical->title,
                            'automatic' => $link->automatic,
                        ],
                    );
                    $link->delete();
                });
            $duplicate->internalLinks()->delete();

            if ($duplicateWasPublished && $duplicatePath !== $canonicalPath) {
                Redirect::query()->updateOrCreate(
                    ['from_path' => $duplicatePath],
                    ['to_path' => $canonicalPath, 'status_code' => 301, 'active' => true],
                );
            }

            return $canonical->fresh();
        });
    }

    private function mergeBodies(string $canonicalBody, string $duplicateBody): string
    {
        $canonicalIntro = $this->intro($canonicalBody);
        $canonicalSections = $this->sections($canonicalBody);
        $duplicateSections = $this->sections($duplicateBody);

        foreach ($duplicateSections as $duplicateSection) {
            $matchKey = collect(array_keys($canonicalSections))->sortByDesc(
                fn (string $key) => $this->detector->similarity($key, $duplicateSection['heading'])
            )->first();
            $similarity = $matchKey ? $this->detector->similarity($matchKey, $duplicateSection['heading']) : 0;
            if ($matchKey && $similarity >= 0.65) {
                if ($this->wordCount($duplicateSection['content']) > $this->wordCount($canonicalSections[$matchKey]['content'])) {
                    $canonicalSections[$matchKey] = $duplicateSection;
                }
            } else {
                $canonicalSections[$duplicateSection['heading']] = $duplicateSection;
            }
        }

        return trim($canonicalIntro."\n\n".collect($canonicalSections)->pluck('content')->implode("\n\n"));
    }

    private function intro(string $body): string
    {
        return trim(preg_split('/^##\s+/mu', $body, 2)[0] ?? '');
    }

    private function sections(string $body): array
    {
        preg_match_all('/^##\s+(.+?)\R(.*?)(?=^##\s+|\z)/msu', $body, $matches, PREG_SET_ORDER);
        $sections = [];
        foreach ($matches as $match) {
            $heading = trim($match[1]);
            $sections[$heading] = [
                'heading' => $heading,
                'content' => '## '.$heading."\n".trim($match[2]),
            ];
        }

        return $sections;
    }

    private function wordCount(string $value): int
    {
        preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($value), $matches);

        return count($matches[0]);
    }

    private function publicPath(Article $article): string
    {
        return match ($article->type) {
            'comparison' => '/comparatifs/'.$article->slug,
            'alternatives' => '/alternatives/'.$article->slug,
            'best_tools' => '/meilleurs-outils/'.$article->slug,
            default => '/blog/'.$article->slug,
        };
    }
}
