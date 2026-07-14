<?php

namespace App\Console\Commands;

use App\Services\ContentScheduler;
use Illuminate\Console\Command;

final class RunContentScheduler extends Command
{
    protected $signature = 'content:scheduler-tick';

    protected $description = 'Synchronise le calendrier éditorial et lance le prochain contenu arrivé à échéance';

    public function handle(ContentScheduler $scheduler): int
    {
        $result = $scheduler->tick();
        $this->line($result['state'].' — '.$result['message']);

        return self::SUCCESS;
    }
}
