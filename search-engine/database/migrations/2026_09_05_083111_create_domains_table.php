<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('base_url', 500);
            $table->text('robots_txt')->nullable();
            $table->integer('crawl_delay_ms')->default(1000);
            $table->enum('status', ['pending', 'active', 'paused', 'blocked'])->default('pending');
            $table->integer('max_depth')->default(5);
            $table->integer('pages_count')->default(0);
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
