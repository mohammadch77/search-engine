<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('url_hash', 64)->unique();
            // Depth/content_type aren't in the original spec but are needed by
            // the processor to enforce max_depth on discovered links and to
            // store Page.content_type without re-fetching.
            $table->integer('depth')->default(0);
            $table->longText('html_content')->nullable();
            $table->smallInteger('http_status')->nullable();
            $table->string('content_type', 100)->nullable();
            $table->text('headers')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->boolean('processed')->default(false);
            // Mirrors crawl_queue's locking pattern so concurrent process
            // workers can safely claim distinct rows.
            $table->string('locked_by', 64)->nullable();
            $table->integer('attempts')->default(0);

            $table->index('processed');
            $table->index('domain_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_pages');
    }
};
