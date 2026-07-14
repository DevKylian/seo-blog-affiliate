<?php

use App\Http\Controllers\BlogController;
use App\Livewire\AccessLogs;
use App\Livewire\ArticleEditor;
use App\Livewire\Articles;
use App\Livewire\Auth\Login;
use App\Livewire\Automation;
use App\Livewire\ContentSchedulerDashboard;
use App\Livewire\ContentStudio;
use App\Livewire\Dashboard;
use App\Livewire\Keywords;
use App\Livewire\Projects;
use App\Livewire\Research;
use App\Livewire\Settings;
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

Route::redirect('/', '/blog');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/outils', [BlogController::class, 'tools'])->name('tools.index');
Route::get('/outils/{slug}/tarifs', [BlogController::class, 'pricing'])->name('tools.pricing');
Route::get('/outils/{slug}', [BlogController::class, 'tool'])->name('tools.show');
Route::get('/comparatifs/{slug}', [BlogController::class, 'comparison'])->name('comparisons.show');
Route::get('/alternatives/{slug}', [BlogController::class, 'alternatives'])->name('alternatives.show');
Route::get('/meilleurs-outils/{slug}', [BlogController::class, 'bestTools'])->name('best-tools.show');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/sitemap.xml', [BlogController::class, 'sitemap'])->name('sitemap');

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
    Route::get('/content', ContentStudio::class)->name('admin.content');
    Route::get('/articles', Articles::class)->name('admin.articles');
    Route::get('/articles/create', ArticleEditor::class)->name('admin.articles.create');
    Route::get('/articles/{article}/preview', [BlogController::class, 'preview'])->name('admin.articles.preview');
    Route::get('/articles/{article}/edit', ArticleEditor::class)->name('admin.articles.edit');
    Route::get('/settings', Settings::class)->name('admin.settings');
    Route::get('/access-logs', AccessLogs::class)->name('admin.logs');
});
