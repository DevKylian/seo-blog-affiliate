<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        $manifestPath = public_path('build/manifest.json');
        if (! file_exists($manifestPath)) {
            $buildDir = public_path('build');
            if (! is_dir($buildDir)) {
                @mkdir($buildDir, 0755, true);
            }
            $assetsDir = public_path('build/assets');
            if (! is_dir($assetsDir)) {
                @mkdir($assetsDir, 0755, true);
            }

            $cssFile = 'assets/app-C4_VP5_d.css';
            $jsFile = 'assets/app-CIomGrQN.js';

            $cssMatches = glob($assetsDir . '/*.css');
            if (! empty($cssMatches)) {
                $cssFile = 'assets/' . basename($cssMatches[0]);
            }

            $jsMatches = glob($assetsDir . '/*.js');
            if (! empty($jsMatches)) {
                $jsFile = 'assets/' . basename($jsMatches[0]);
            }

            $manifestContent = json_encode([
                'resources/css/app.css' => [
                    'file' => $cssFile,
                    'src' => 'resources/css/app.css',
                    'isEntry' => true,
                ],
                'resources/js/app.js' => [
                    'file' => $jsFile,
                    'name' => 'app',
                    'src' => 'resources/js/app.js',
                    'isEntry' => true,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            @file_put_contents($manifestPath, $manifestContent);
        }
    }
}
