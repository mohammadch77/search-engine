<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query', 500);
            $table->integer('results_count')->default(0);
            $table->integer('response_time_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('searched_at');

            $table->index('searched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
