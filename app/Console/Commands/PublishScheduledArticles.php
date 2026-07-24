<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PublishScheduledArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'content:publish-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish articles that have reached their scheduled publication date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for scheduled articles to publish...');

        $articles = \App\Models\Article::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($articles->isEmpty()) {
            $this->info('No scheduled articles to publish at this time.');
            return;
        }

        foreach ($articles as $article) {
            $article->update([
                'status' => 'published',
                'published_at' => $article->scheduled_at,
            ]);
            $this->line("Published article ID: {$article->id} - {$article->title}");
        }

        $this->info("Successfully published {$articles->count()} articles.");
    }
}
