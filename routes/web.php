<?php

use App\Http\Controllers\AffiliateRedirectController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\IndexNowKeyController;
use App\Livewire\AccessLogs;
use App\Livewire\ArticleEditor;
use App\Livewire\Articles;
use App\Livewire\Auth\Login;
use App\Livewire\Automation;
use App\Livewire\ContentSchedulerDashboard;
use App\Livewire\Dashboard;
use App\Livewire\Keywords;
use App\Livewire\Projects;
use App\Livewire\Research;
use App\Livewire\Settings;
use App\Services\DevGenerationCircuitBreaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [BlogController::class, 'home'])->name('home');
Route::get('/auteurs/kylian-dev', [BlogController::class, 'about'])->name('author.show');
Route::view('/mentions-legales', 'pages.mentions-legales')->name('mentions-legales');
Route::view('/politique-confidentialite', 'pages.politique-confidentialite')->name('politique-confidentialite');
Route::view('/criteres-selection', 'pages.criteres')->name('criteres');
Route::view('/sources', 'pages.sources')->name('sources');
Route::view('/tests-logiciels', 'pages.tests')->name('tests');
Route::redirect('/home', '/admin');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/outils-gratuits', [BlogController::class, 'freeTools'])->name('free-tools.index');
Route::get('/outils-gratuits/{slug}', [BlogController::class, 'freeTool'])->name('free-tools.show');
Route::get('/outils', [BlogController::class, 'tools'])->name('tools.index');
Route::get('/outils/{slug}/tarifs', [BlogController::class, 'pricing'])->name('tools.pricing');
Route::get('/outils/{slug}', [BlogController::class, 'tool'])->name('tools.show');
Route::get('/comparatifs/{slug}', [BlogController::class, 'comparison'])->name('comparisons.show');
Route::get('/alternatives/{slug}', [BlogController::class, 'alternatives'])->name('alternatives.show');
Route::get('/meilleurs-outils/{slug}', [BlogController::class, 'bestTools'])->name('best-tools.show');
Route::get('/avis/{slug}', [BlogController::class, 'review'])->name('reviews.show');
Route::get('/guides/{slug}', [BlogController::class, 'guide'])->name('guides.show');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/go/{project:slug}', AffiliateRedirectController::class)->name('affiliate.redirect');
Route::get('/indexnow/{key}.txt', IndexNowKeyController::class)->where('key', '[A-Za-z0-9_-]{8,128}')->name('indexnow.key');
Route::get('/sitemap.xml', [BlogController::class, 'sitemap'])->name('sitemap');
Route::get('/sitemap{any}.xml', function () {
    return redirect()->route('sitemap', [], 301);
})->where('any', '.*');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', Dashboard::class)->name('admin.dashboard');
    Route::get('/automation', Automation::class)->name('admin.automation');
    Route::get('/scheduler', ContentSchedulerDashboard::class)->name('admin.scheduler');
    Route::get('/projects', Projects::class)->name('admin.projects');
    Route::get('/research', Research::class)->name('admin.research');
    Route::get('/keywords', Keywords::class)->name('admin.keywords');
    Route::get('/newsletter', \App\Livewire\Admin\NewsletterSubscribers::class)->name('admin.newsletter');
    Route::get('/articles', Articles::class)->name('admin.articles');
    Route::get('/articles/create', ArticleEditor::class)->name('admin.articles.create');
    Route::get('/articles/{article}/preview', [BlogController::class, 'preview'])->name('admin.articles.preview');
    Route::get('/articles/{article}/edit', ArticleEditor::class)->name('admin.articles.edit');
    Route::get('/settings', Settings::class)->name('admin.settings');
    Route::get('/access-logs', AccessLogs::class)->name('admin.logs');
    Route::post('/dev/stop-generations', function (DevGenerationCircuitBreaker $breaker) {
        abort_unless(app()->isLocal(), 404);

        $result = $breaker->stopAll();

        return back()->with(
            'dev_generation_stop',
            "Generations coupees : {$result['runs']} campagne(s), {$result['items']} contenu(s), {$result['tasks']} tache(s), {$result['plans']} plan(s), {$result['articles']} regeneration(s) article.",
        );
    })->name('admin.dev.stop-generations');
});
