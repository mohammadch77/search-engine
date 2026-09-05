<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('url', 2048);
            $table->smallInteger('status_code')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->integer('content_size_bytes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('crawled_at');

            $table->index(['domain_id', 'crawled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_logs');
    }
};
