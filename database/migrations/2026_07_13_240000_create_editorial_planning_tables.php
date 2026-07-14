<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias')->unique();
            $table->string('canonical_topic')->index();
            $table->timestamps();
        });

        Schema::create('editorial_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('requested_count');
            $table->unsignedSmallInteger('candidate_count')->default(0);
            $table->unsignedSmallInteger('accepted_count')->default(0);
            $table->unsignedSmallInteger('rejected_count')->default(0);
            $table->unsignedSmallInteger('duplicate_count')->default(0);
            $table->unsignedSmallInteger('weak_angle_count')->default(0);
            $table->unsignedSmallInteger('source_gap_count')->default(0);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('status')->default('planning')->index();
            $table->text('instructions')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('editorial_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('closest_article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->foreignId('replacement_for_id')->nullable()->constrained('editorial_ideas')->nullOnDelete();
            $table->string('title');
            $table->string('primary_keyword');
            $table->string('entity_key', 150);
            $table->string('topic_key', 150)->index();
            $table->string('intent', 50);
            $table->string('angle', 150);
            $table->string('audience', 150);
            $table->text('problem');
            $table->text('expected_outcome');
            $table->string('funnel_stage', 50);
            $table->text('unique_promise');
            $table->json('excluded_topics');
            $table->json('outline');
            $table->string('fingerprint', 700)->index();
            $table->string('content_type', 50);
            $table->string('status')->default('candidate')->index();
            $table->text('rejection_reason')->nullable();
            $table->decimal('seo_score', 6, 2)->default(0);
            $table->decimal('similarity_score', 5, 2)->default(0);
            $table->decimal('source_coverage', 5, 2)->default(0);
            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedTinyInteger('generation_attempts')->default(0);
            $table->timestamps();
            $table->index(['editorial_plan_id', 'status']);
        });

        Schema::table('content_runs', function (Blueprint $table) {
            $table->foreignId('editorial_plan_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
        Schema::table('content_run_items', function (Blueprint $table) {
            $table->foreignId('editorial_idea_id')->nullable()->after('content_run_id')->constrained()->nullOnDelete();
        });
        Schema::table('content_briefs', function (Blueprint $table) {
            $table->text('editorial_problem')->nullable()->after('unique_promise');
            $table->text('expected_outcome')->nullable()->after('editorial_problem');
        });
        Schema::table('articles', function (Blueprint $table) {
            $table->text('editorial_problem')->nullable()->after('unique_promise');
            $table->text('expected_outcome')->nullable()->after('editorial_problem');
        });

        $now = now();
        DB::table('topic_aliases')->insert(collect([
            'gestion-clients', 'gestion-client', 'gestion-relations-clients', 'gestion-relation-client',
            'relation-client', 'gestion-clientele', 'utilisation-crm',
        ])->map(fn (string $alias) => [
            'alias' => $alias,
            'canonical_topic' => 'gestion-relation-client',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::table('articles', fn (Blueprint $table) => $table->dropColumn(['editorial_problem', 'expected_outcome']));
        Schema::table('content_briefs', fn (Blueprint $table) => $table->dropColumn(['editorial_problem', 'expected_outcome']));
        Schema::table('content_run_items', function (Blueprint $table) {
            $table->dropForeign(['editorial_idea_id']);
            $table->dropColumn('editorial_idea_id');
        });
        Schema::table('content_runs', function (Blueprint $table) {
            $table->dropForeign(['editorial_plan_id']);
            $table->dropColumn('editorial_plan_id');
        });
        Schema::dropIfExists('editorial_ideas');
        Schema::dropIfExists('editorial_plans');
        Schema::dropIfExists('topic_aliases');
    }
};
