<?php

namespace App\Services;

final class EditorialPlanWorkerLauncher
{
    public function __construct(
        private readonly BackgroundArtisanLauncher $launcher,
        private readonly RuntimeBinaryLocator $binaries,
    ) {}

    public function launch(int $planId): void
    {
        $this->launcher->launch('editorial:plan-worker', $planId, "editorial-plan-{$planId}.log");
    }

    public function resolveCliBinary(?string $runtimeBinary = null): string
    {
        $configured = function_exists('app') && app()->bound('config')
            ? (string) config('services.runtime.php_binary')
            : null;

        return $this->binaries->resolvePhp($runtimeBinary, $configured);
    }
}
