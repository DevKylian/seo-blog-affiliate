<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

final class RuntimeBinaryLocator
{
    public function __construct(private readonly ExecutableFinder $finder = new ExecutableFinder) {}

    public function resolvePhp(?string $runtimeBinary = null, ?string $configuredBinary = null): string
    {
        $runtimeBinary ??= PHP_BINARY;
        $suffix = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        $runtimeDirectory = dirname($runtimeBinary);
        $runtimeName = mb_strtolower(basename($runtimeBinary));
        preg_match('/(\d+(?:\.\d+)?)/', $runtimeName, $version);

        $candidates = array_filter([
            $configuredBinary,
            $this->isPhpCliName($runtimeName) ? $runtimeBinary : null,
            $runtimeDirectory.DIRECTORY_SEPARATOR.'php'.$suffix,
            PHP_BINDIR.DIRECTORY_SEPARATOR.'php'.$suffix,
            isset($version[1]) ? $this->finder->find('php'.$version[1]) : null,
            $this->finder->find('php'),
        ]);

        $binary = $this->firstExecutable($candidates);
        if ($binary === null || ! $this->isPhpCliName(mb_strtolower(basename($binary)))) {
            throw new RuntimeException('Binaire PHP CLI introuvable. Configurez PHP_CLI_BINARY avec le chemin de php ou php.exe.');
        }

        return $binary;
    }

    public function resolveNode(?string $configuredBinary = null): ?string
    {
        $home = $this->homeDirectory();
        $programFiles = (string) (getenv('ProgramFiles') ?: getenv('PROGRAMFILES'));
        $appData = (string) getenv('APPDATA');
        $nvmSymlink = (string) getenv('NVM_SYMLINK');

        return $this->firstExecutable(array_filter([
            $configuredBinary,
            $this->finder->find('node'),
            $this->finder->find('node.exe'),
            $programFiles !== '' ? $programFiles.'\\nodejs\\node.exe' : null,
            $nvmSymlink !== '' ? rtrim($nvmSymlink, '\\/').'\\node.exe' : null,
            $appData !== '' ? $appData.'\\nvm\\current\\node.exe' : null,
            '/opt/homebrew/bin/node',
            '/usr/local/bin/node',
            '/usr/bin/node',
            $home !== '' ? $home.'/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node' : null,
        ]));
    }

    public function resolveBrowser(?string $configuredBinary = null): ?string
    {
        $programFiles = (string) (getenv('ProgramFiles') ?: getenv('PROGRAMFILES'));
        $programFilesX86 = (string) (getenv('ProgramFiles(x86)') ?: getenv('PROGRAMFILES(X86)'));
        $localAppData = (string) getenv('LOCALAPPDATA');

        return $this->firstExecutable(array_filter([
            $configuredBinary,
            $this->finder->find('google-chrome-stable'),
            $this->finder->find('google-chrome'),
            $this->finder->find('chromium'),
            $this->finder->find('chromium-browser'),
            $this->finder->find('microsoft-edge'),
            $this->finder->find('chrome'),
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',
            '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
            '/Applications/Brave Browser.app/Contents/MacOS/Brave Browser',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/microsoft-edge',
            $programFiles !== '' ? $programFiles.'\\Google\\Chrome\\Application\\chrome.exe' : null,
            $programFilesX86 !== '' ? $programFilesX86.'\\Google\\Chrome\\Application\\chrome.exe' : null,
            $localAppData !== '' ? $localAppData.'\\Google\\Chrome\\Application\\chrome.exe' : null,
            $programFiles !== '' ? $programFiles.'\\Microsoft\\Edge\\Application\\msedge.exe' : null,
            $programFilesX86 !== '' ? $programFilesX86.'\\Microsoft\\Edge\\Application\\msedge.exe' : null,
            $programFiles !== '' ? $programFiles.'\\BraveSoftware\\Brave-Browser\\Application\\brave.exe' : null,
        ]));
    }

    public function resolveShell(): ?string
    {
        return $this->firstExecutable(array_filter([$this->finder->find('sh'), '/bin/sh']));
    }

    public function resolveNohup(): ?string
    {
        return $this->firstExecutable(array_filter([$this->finder->find('nohup'), '/usr/bin/nohup', '/bin/nohup']));
    }

    /** @param array<int, string> $candidates */
    private function firstExecutable(array $candidates): ?string
    {
        foreach (array_unique($candidates) as $candidate) {
            if (! str_contains($candidate, '/') && ! str_contains($candidate, '\\')) {
                $candidate = $this->finder->find($candidate) ?: $candidate;
            }
            $resolved = realpath($candidate) ?: $candidate;
            if (is_file($resolved) && (PHP_OS_FAMILY === 'Windows' || is_executable($resolved))) {
                return $resolved;
            }
        }

        return null;
    }

    private function isPhpCliName(string $name): bool
    {
        return preg_match('/(?:fpm|cgi)/i', $name) !== 1;
    }

    private function homeDirectory(): string
    {
        return rtrim((string) (getenv('HOME') ?: getenv('USERPROFILE')), '\\/');
    }
}
