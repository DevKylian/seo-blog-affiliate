<?php

namespace App\Console\Commands;

use App\Models\EditorialPlan;
use App\Services\EditorialPlanBuilder;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class RunEditorialPlanWorker extends Command
{
    protected $signature = 'editorial:plan-worker {planId : Identifiant du plan éditorial}';

    protected $description = 'Construit un plan éditorial en arrière-plan, indépendamment du navigateur';

    public function handle(EditorialPlanBuilder $builder): int
    {
        $planId = (int) $this->argument('planId');
        if (! EditorialPlan::query()->whereKey($planId)->exists()) {
            $this->error("Plan {$planId} introuvable.");

            return self::FAILURE;
        }

        $lock = Cache::lock("editorial-plan-worker:{$planId}", 21600);
        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            $this->info("Worker de planification démarré pour le plan {$planId}.");
            while (true) {
                $plan = EditorialPlan::query()->find($planId);
                if (! $plan || $plan->status !== 'planning') {
                    break;
                }

                try {
                    $plan = $builder->advance($plan);
                    $this->line(now()->format('H:i:s')." — étape {$plan->attempts} — {$plan->status} — {$plan->candidate_count} idées analysées");
                } catch (Throwable $exception) {
                    if ($this->isRecoverable($exception)) {
                        $this->warn(now()->format('H:i:s').' — Gemini indisponible, nouvelle tentative dans 5 secondes.');
                        sleep(5);

                        continue;
                    }

                    \Illuminate\Support\Facades\Log::error("WORKER FATAL: " . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
                    EditorialPlan::query()->whereKey($planId)->where('status', 'planning')->update([
                        'status' => 'failed',
                        'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                    ]);
                    $this->error($exception->getMessage());

                    return self::FAILURE;
                }

                if ($plan->status !== 'planning') {
                    break;
                }
                sleep(1);
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    private function isRecoverable(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return $exception instanceof ConnectionException
            || str_contains($message, 'high demand')
            || str_contains($message, 'high traffic')
            || str_contains($message, 'too many requests')
            || str_contains($message, 'resource exhausted')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'curl error 28')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'limite de sortie')
            || str_contains($message, 'structuré')
            || str_contains($message, 'gemini http')
            || preg_match('/(?:gemini\s+)?http\s+(?:429|503)\b/u', $message) === 1;
    }
}
