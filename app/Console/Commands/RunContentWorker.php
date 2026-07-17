<?php

namespace App\Console\Commands;

use App\Models\ContentRun;
use App\Services\ContentRunProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class RunContentWorker extends Command
{
    protected $signature = 'content:run-worker {runId : Identifiant de la campagne}';

    protected $description = 'Traite une campagne de contenus en arrière-plan jusqu’à sa fin ou son arrêt';

    public function handle(ContentRunProcessor $processor): int
    {
        $runId = (int) $this->argument('runId');
        if (! ContentRun::query()->whereKey($runId)->exists()) {
            $this->error("Campagne {$runId} introuvable.");

            return self::FAILURE;
        }

        $lock = Cache::lock("content-run-worker:{$runId}", 21600);
        if (! $lock->get()) {
            $this->line("Un worker traite déjà la campagne {$runId}.");

            return self::SUCCESS;
        }

        try {
            $this->info("Worker autonome démarré pour la campagne {$runId}.");
            while (true) {
                $result = $processor->process($runId);
                $this->line(now()->format('H:i:s').' — '.$result['state'].' — '.$result['message']);

                if (in_array($result['state'], ['finished', 'stopped'], true)) {
                    break;
                }

                sleep($result['state'] === 'retry' ? $this->retryDelaySeconds((int) ($result['attempt'] ?? 1)) : 1);
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    private function retryDelaySeconds(int $attempt): int
    {
        return min(300, 5 * (2 ** min(6, max(0, $attempt - 1))));
    }
}
