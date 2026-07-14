<?php

namespace App\Console\Commands;

use App\Models\ContentSchedule;
use App\Services\ContentScheduler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class RunContentSchedulerWorker extends Command
{
    protected $signature = 'content:scheduler-worker {scheduleId : Identifiant de la programmation}';

    protected $description = 'Maintient la Content Factory active en local, indépendamment du navigateur';

    public function handle(ContentScheduler $scheduler): int
    {
        $scheduleId = (int) $this->argument('scheduleId');
        if (! ContentSchedule::query()->whereKey($scheduleId)->exists()) {
            $this->error("Programmation {$scheduleId} introuvable.");

            return self::FAILURE;
        }

        $lock = Cache::lock("content-scheduler-worker:{$scheduleId}", 604800);
        if (! $lock->get()) {
            $this->line("Le worker de la programmation {$scheduleId} est déjà actif.");

            return self::SUCCESS;
        }

        try {
            $this->info("Content Factory {$scheduleId} démarrée.");
            while (ContentSchedule::query()->whereKey($scheduleId)->where('is_active', true)->exists()) {
                $result = $scheduler->tick();
                $this->line(now()->format('H:i:s').' — '.$result['state'].' — '.$result['message']);
                sleep(30);
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
