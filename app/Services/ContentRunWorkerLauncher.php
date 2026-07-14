<?php

namespace App\Services;

final class ContentRunWorkerLauncher
{
    public function __construct(
        private readonly BackgroundArtisanLauncher $launcher,
        private readonly RuntimeBinaryLocator $binaries,
    ) {}

    public function launch(int $runId): void
    {
        $this->launcher->launch('content:run-worker', $runId, "content-run-{$runId}.log");
    }

    public function resolveCliBinary(?string $runtimeBinary = null): string
    {
        $configured = function_exists('app') && app()->bound('config')
            ? (string) config('services.runtime.php_binary')
            : null;

        return $this->binaries->resolvePhp($runtimeBinary, $configured);
    }
}
