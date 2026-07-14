<?php

namespace App\Services;

final class ContentSchedulerWorkerLauncher
{
    public function __construct(private readonly BackgroundArtisanLauncher $launcher) {}

    public function launch(int $scheduleId): void
    {
        $this->launcher->launch('content:scheduler-worker', $scheduleId, "content-scheduler-{$scheduleId}.log");
    }
}
