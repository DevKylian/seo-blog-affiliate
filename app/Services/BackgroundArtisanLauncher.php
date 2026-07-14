<?php

namespace App\Services;

use RuntimeException;

final class BackgroundArtisanLauncher
{
    public function __construct(private readonly RuntimeBinaryLocator $binaries) {}

    public function launch(string $artisanCommand, int $identifier, string $logName): void
    {
        if (preg_match('/^[a-z0-9:_-]+$/i', $artisanCommand) !== 1) {
            throw new RuntimeException('Commande de worker invalide.');
        }

        $identifier = max(1, $identifier);
        $php = $this->binaries->resolvePhp(PHP_BINARY, (string) config('services.runtime.php_binary'));
        $artisan = base_path('artisan');
        $log = storage_path('logs/'.$logName);

        if (PHP_OS_FAMILY !== 'Windows'
            && function_exists('pcntl_fork')
            && function_exists('posix_setsid')
            && function_exists('pcntl_exec')) {
            $this->launchDetachedUnix($artisanCommand, $identifier, $php, $artisan, $log);

            return;
        }

        if (! function_exists('exec')) {
            throw new RuntimeException('La fonction PHP exec est désactivée : impossible de lancer le worker autonome.');
        }

        $nohup = PHP_OS_FAMILY === 'Windows' ? null : $this->binaries->resolveNohup();
        if (PHP_OS_FAMILY !== 'Windows' && $nohup === null) {
            throw new RuntimeException('La commande nohup est introuvable : configurez un gestionnaire de workers en production.');
        }

        $command = $this->buildDetachedCommand(
            $artisanCommand,
            $identifier,
            $php,
            $artisan,
            $log,
            PHP_OS_FAMILY,
            $nohup,
        );
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException("Impossible de démarrer le worker autonome {$artisanCommand}.");
        }
    }

    public function buildDetachedCommand(
        string $artisanCommand,
        int $identifier,
        string $php,
        string $artisan,
        string $log,
        string $osFamily,
        ?string $nohup = null,
    ): string {
        if ($osFamily === 'Windows') {
            return sprintf(
                'start "" /B %s %s %s %d >> %s 2>&1 < NUL',
                $this->quoteWindows($php),
                $this->quoteWindows($artisan),
                $artisanCommand,
                max(1, $identifier),
                $this->quoteWindows($log),
            );
        }

        if ($nohup === null || $nohup === '') {
            throw new RuntimeException('Le chemin de nohup est requis sur macOS et Linux.');
        }

        return sprintf(
            '%s %s %s %s %d >> %s 2>&1 < /dev/null &',
            escapeshellarg($nohup),
            escapeshellarg($php),
            escapeshellarg($artisan),
            $artisanCommand,
            max(1, $identifier),
            escapeshellarg($log),
        );
    }

    private function launchDetachedUnix(string $artisanCommand, int $identifier, string $php, string $artisan, string $log): void
    {
        pcntl_signal(SIGCHLD, SIG_IGN);
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Impossible de créer le processus autonome.');
        }
        if ($pid > 0) {
            return;
        }
        if (posix_setsid() === -1) {
            exit(1);
        }

        $shell = $this->binaries->resolveShell();
        if ($shell === null) {
            exit(1);
        }
        $command = sprintf(
            'cd %s && exec %s %s %s %d >> %s 2>&1 < /dev/null',
            escapeshellarg(base_path()),
            escapeshellarg($php),
            escapeshellarg($artisan),
            $artisanCommand,
            $identifier,
            escapeshellarg($log),
        );
        pcntl_exec($shell, ['-c', $command]);
        exit(1);
    }

    private function quoteWindows(string $value): string
    {
        if (str_contains($value, '"')) {
            throw new RuntimeException('Un chemin d’exécution contient un guillemet invalide.');
        }

        return '"'.str_replace('%', '%%', $value).'"';
    }
}
