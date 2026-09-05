<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('url_hash', 64)->unique();
            $table->string('title', 500)->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->longText('content_raw')->nullable();
            $table->longText('content_text')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->smallInteger('http_status')->nullable();
            $table->string('content_type', 100)->nullable();
            $table->string('language', 10)->nullable();
            $table->integer('word_count')->default(0);
            $table->integer('depth')->default(0);
            $table->float('page_rank')->default(0);
            $table->enum('status', ['pending', 'indexed', 'error'])->default('pending');
            $table->timestamp('crawled_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index(['domain_id', 'status']);
            $table->index('url_hash');
        });

        DB::statement('ALTER TABLE pages ADD FULLTEXT fulltext_title_content (title, content_text)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
