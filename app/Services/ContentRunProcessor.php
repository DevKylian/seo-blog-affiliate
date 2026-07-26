<?php

namespace App\Services;

use App\Exceptions\DuplicateContentException;
use App\Exceptions\PlannedContentRejectedException;
use App\Models\ContentRun;
use App\Models\ContentRunItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ContentRunProcessor
{
    public function __construct(
        private readonly GeminiContentGenerator $generator,
        private readonly EditorialPlanBuilder $planner,
    ) {}

    /** @return array{state:string,message:string,error:string,remaining:int,attempt?:int} */
    public function process(int $runId): array
    {
        $lock = Cache::lock("content-run-step:{$runId}", 120);
        if (! $lock->get()) {
            return $this->result('busy', 'Une étape de rédaction est déjà traitée en arrière-plan.');
        }

        try {
            return $this->processLocked($runId);
        } finally {
            $lock->release();
        }
    }

    /** @return array{state:string,message:string,error:string,remaining:int,attempt?:int} */
    private function processLocked(int $runId): array
    {
        $this->allowLongRequest();
        $run = ContentRun::query()->with(['project', 'editorialPlan'])->find($runId);
        if (! $run || ! in_array($run->status, ['pending', 'processing'], true)) {
            return $this->result('finished', '', '', 0);
        }

        Auth::onceUsingId($run->user_id);
        $this->recoverInterruptedItems($run, 3);
        if ($run->items()->where('status', 'processing')->exists()) {
            return $this->result('busy', 'Une génération est encore en cours.', '', $run->items()->where('status', 'pending')->count());
        }

        $item = $run->items()->where('status', 'pending')->orderBy('id')->first();
        if (! $item) {
            return $this->completeRun($run);
        }

        $run->update(['status' => 'processing', 'started_at' => $run->started_at ?? now()]);
        $item->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $idea = $item->editorialIdea;
            if ($idea) {
                if ($item->generation_step === 0) {
                    $idea->increment('generation_attempts');
                }
                $idea->update(['status' => 'generating']);
                $parts = $item->generation_parts ?? [];
                $step = (int) $item->generation_step;
                $partCount = $this->generator->partCount(
                    $item->content_type,
                    $idea->title,
                    $idea->intent,
                    $idea->funnel_stage,
                    $idea->primary_keyword,
                    array_values($parts),
                );

                $instructions = (string) $run->instructions;
                if ($item->error_message && !str_starts_with($item->error_message, 'Gemini') && !str_starts_with($item->error_message, 'La partie')) {
                    $instructions .= "\n\nCORRECTION OBLIGATOIRE SUITE À REFUS PRÉCÉDENT :\nLe brouillon a été refusé pour cette raison : {$item->error_message}\n- Réécris intégralement le contenu en corrigeant cette erreur de manière stricte.\n- N'invente JAMAIS d'outil, de marque, ou de concurrent fictif.";
                }

                if ($step < $partCount) {
                    $parts[$step] = $this->generator->generatePartFromIdea(
                        $run->project,
                        $idea,
                        $instructions,
                        $step,
                        (int) $item->api_attempts,
                        array_values($parts),
                    );
                    $nextStep = $step + 1;
                    $item->update([
                        'generation_parts' => array_values($parts),
                        'generation_step' => $nextStep,
                        'api_attempts' => 0,
                        'error_message' => null,
                    ]);

                    if ($nextStep < $partCount) {
                        $item->update(['status' => 'pending', 'started_at' => null]);

                        return $this->result(
                            'progressed',
                            "« {$idea->title} » : partie {$nextStep}/{$partCount} enregistrée.",
                            '',
                            $run->items()->where('status', 'pending')->count(),
                        );
                    }
                }

                $article = $this->generator->finalizeFromIdeaParts($run->project, $idea, $instructions, $parts);
            } else {
                $instructions = (string) $run->instructions;
                if ($item->error_message && !str_starts_with($item->error_message, 'Gemini') && !str_starts_with($item->error_message, 'La partie')) {
                    $instructions .= "\n\nCORRECTION OBLIGATOIRE SUITE À REFUS PRÉCÉDENT :\nLe brouillon a été refusé pour cette raison : {$item->error_message}\n- Réécris intégralement le contenu en corrigeant cette erreur de manière stricte.\n- N'invente JAMAIS d'outil, de marque, ou de concurrent fictif.";
                }
                $article = $this->generator->generate($run->project, $item->content_type, $item->keyword, $instructions);
            }

            if ($run->publication_days > 0) {
                $gapInMinutes = (int) round(($run->publication_days * 24 * 60) / max(1, $run->requested_count));
                $latestDate = \App\Models\Article::query()
                    ->where('seo_project_id', $run->seo_project_id)
                    ->whereIn('status', ['published', 'scheduled'])
                    ->max(DB::raw('COALESCE(scheduled_at, published_at)'));

                $baseDate = $latestDate ? \Carbon\Carbon::parse($latestDate) : now();
                $newDate = $baseDate->copy()->addMinutes($gapInMinutes);

                $article->update([
                    'status' => 'scheduled',
                    'scheduled_at' => $newDate,
                ]);
            } else {
                $article->update([
                    'status' => 'published',
                    'published_at' => now(),
                ]);
            }

            $item->update(['article_id' => $article->id, 'status' => 'completed', 'completed_at' => now(), 'started_at' => null]);
            $idea?->update(['status' => 'generated']);
            $run->increment('completed_count');
        } catch (DuplicateContentException $exception) {
            $this->replaceRejectedItem($run, $item, $exception);
        } catch (PlannedContentRejectedException $exception) {
            if ($item->api_attempts < 3) {
                $apiAttempts = (int) $item->api_attempts + 1;
                $item->update([
                    'status' => 'pending',
                    'api_attempts' => $apiAttempts,
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'started_at' => null,
                    'generation_step' => 0,
                    'generation_parts' => [],
                ]);
                $run->update(['status' => 'pending']);
                return [
                    ...$this->result('retry', "Correction IA (tentative {$apiAttempts}) : {$exception->getMessage()}", '', $run->items()->where('status', 'pending')->count()),
                    'attempt' => $apiAttempts,
                ];
            }
            $this->replaceRejectedItem($run, $item, $exception);
        } catch (Throwable $exception) {
            if ($this->isRecoverableGenerationError($exception)) {
                $apiAttempts = min(255, (int) $item->api_attempts + 1);
                $isMissingSources = str_contains($exception->getMessage(), '[S1]');
                $item->update([
                    'status' => 'pending',
                    'api_attempts' => $isMissingSources ? 0 : $apiAttempts,
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'started_at' => null,
                    'generation_step' => $isMissingSources ? 0 : $item->generation_step,
                    'generation_parts' => $isMissingSources ? [] : $item->generation_parts,
                ]);
                $run->update(['status' => 'pending']);
                $reason = match (true) {
                    $this->isCapacityError($exception) && $apiAttempts > 3 => 'Gemini Flash de secours est également saturé',
                    $this->isCapacityError($exception) && $apiAttempts === 3 => 'Gemini Flash-Lite est saturé ; le secours Gemini Flash sera utilisé au prochain essai',
                    $this->isCapacityError($exception) => 'Gemini Flash-Lite est saturé',
                    $this->isTimeoutError($exception) => 'Gemini n’a pas répondu à temps',
                    default => 'La partie reçue est incomplète',
                };

                return [
                    ...$this->result('retry', "{$reason} (tentative {$apiAttempts}). Nouvelle rédaction automatique dans 5 secondes, sans perte de contenu.", '', $run->items()->where('status', 'pending')->count()),
                    'attempt' => $apiAttempts,
                ];
            }

            report($exception);
            if ($this->isFatalRunError($exception)) {
                $this->stopRunAfterTechnicalError($run, $item, $exception);

                return $this->result('stopped', '', 'Campagne stoppée automatiquement : '.$exception->getMessage(), 0);
            }

            $this->markItemFailedAndContinue($run, $item, $exception);

            $remaining = $run->items()->where('status', 'pending')->count();
            if ($remaining === 0) {
                return $this->completeRun($run->fresh());
            }

            return $this->result('progressed', 'Échec technique sur un contenu. La production continue automatiquement sur les suivants.', '', $remaining);
        }

        $remaining = $run->items()->where('status', 'pending')->count();
        if ($remaining === 0) {
            return $this->completeRun($run->fresh());
        }

        return $this->result('progressed', 'Partie enregistrée. La production continue automatiquement.', '', $remaining);
    }

    private function replaceRejectedItem(ContentRun $run, ContentRunItem $item, Throwable $exception): void
    {
        $item->update([
            'status' => 'rejected',
            'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            'completed_at' => now(),
            'started_at' => null,
        ]);

        try {
            if (! $run->editorialPlan || ! $item->editorialIdea) {
                $item->update(['status' => 'skipped']);

                return;
            }
            $replacement = $this->planner->replacementFor($run->editorialPlan, $item->editorialIdea);
            $run->items()->create([
                'editorial_idea_id' => $replacement->id,
                'keyword_id' => $replacement->keyword_id,
                'content_type' => $replacement->content_type,
                'status' => 'pending',
            ]);
        } catch (Throwable $replacementError) {
            $item->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage().' '.$replacementError->getMessage(), 0, 2000),
            ]);
            $run->increment('failed_count');
        }
    }

    /** @return array{state:string,message:string,error:string,remaining:int} */
    private function completeRun(ContentRun $run): array
    {
        $skippedCount = $run->items()->whereIn('status', ['skipped', 'rejected'])->count();
        $status = match (true) {
            $run->failed_count > 0 => 'completed_with_errors',
            $run->completed_count >= $run->requested_count => 'completed',
            $skippedCount > 0 => 'completed_with_warnings',
            default => 'completed',
        };
        $run->update(['status' => $status, 'completed_at' => now()]);
        if ($run->editorialPlan && ! $run->editorialPlan->content_schedule_id && $run->completed_count >= $run->requested_count) {
            $run->editorialPlan->update(['status' => 'completed']);
        }

        return $this->result(
            'finished',
            "Campagne terminée : {$run->completed_count} contenus validés, {$skippedCount} brouillons remplacés, {$run->failed_count} échecs techniques.",
            '',
            0,
        );
    }

    private function recoverInterruptedItems(ContentRun $run, int $minimumAgeMinutes): void
    {
        $items = $run->items()
            ->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes($minimumAgeMinutes))
            ->with('editorialIdea')
            ->get();
        foreach ($items as $item) {
            $item->update(['status' => 'pending', 'started_at' => null]);
            if ($item->editorialIdea?->status === 'generating') {
                $item->editorialIdea->update(['status' => 'accepted']);
            }
        }
    }

    private function isCapacityError(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'high demand')
            || str_contains($message, 'high traffic')
            || str_contains($message, 'too many requests')
            || str_contains($message, 'resource exhausted')
            || str_contains($message, 'rate limit')
            || preg_match('/(?:gemini\s+)?http\s+(?:429|503)\b/u', $message) === 1
            || preg_match('/statut http (?:429|503)\b/u', $message) === 1;
    }

    private function isTimeoutError(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return $exception instanceof ConnectionException
            || str_contains($message, 'curl error 28')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'délai d’attente')
            || str_contains($message, 'connection reset');
    }

    private function isRecoverableGenerationError(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return $this->isCapacityError($exception)
            || $this->isTimeoutError($exception)
            || str_contains($message, 'réponse gemini incomplète')
            || str_contains($message, 'contenu structuré exploitable');
    }

    private function isFatalRunError(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'api key')
            || str_contains($message, 'clé api')
            || str_contains($message, 'unauthorized')
            || str_contains($message, 'forbidden')
            || str_contains($message, 'database');
    }

    private function stopRunAfterTechnicalError(ContentRun $run, ContentRunItem $failedItem, Throwable $exception): void
    {
        DB::transaction(function () use ($run, $failedItem, $exception): void {
            $items = $run->items()->whereIn('status', ['pending', 'processing'])->with('editorialIdea')->get();
            foreach ($items as $item) {
                $message = $item->is($failedItem)
                    ? $exception->getMessage()
                    : 'Campagne stoppée automatiquement après une erreur technique sur un autre contenu.';
                $item->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($message, 0, 2000),
                    'started_at' => null,
                    'completed_at' => now(),
                ]);
                if ($item->editorialIdea?->status === 'generating') {
                    $item->editorialIdea->update(['status' => 'accepted']);
                }
            }
            $run->update([
                'status' => 'completed_with_errors',
                'failed_count' => $run->items()->where('status', 'failed')->count(),
                'completed_at' => now(),
            ]);
            if ($run->editorialPlan && $run->editorialPlan->status === 'generating') {
                $run->editorialPlan->update(['status' => 'locked']);
            }
        });
    }

    private function markItemFailedAndContinue(ContentRun $run, ContentRunItem $failedItem, Throwable $exception): void
    {
        DB::transaction(function () use ($run, $failedItem, $exception): void {
            $failedItem->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'started_at' => null,
                'completed_at' => now(),
            ]);
            if ($failedItem->editorialIdea?->status === 'generating') {
                $failedItem->editorialIdea->update(['status' => 'accepted']);
            }
            $run->increment('failed_count');
        });
    }

    /** @return array{state:string,message:string,error:string,remaining:int} */
    private function result(string $state, string $message = '', string $error = '', int $remaining = 0): array
    {
        return compact('state', 'message', 'error', 'remaining');
    }

    private function allowLongRequest(): void
    {
        if (function_exists('set_time_limit')) {
            @ini_set('max_execution_time', '180');
            @set_time_limit(180);
        }
    }
}
