<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_page_id')->constrained('pages')->cascadeOnDelete();
            $table->foreignId('target_page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('target_url', 2048);
            $table->string('anchor_text', 500)->nullable();
            $table->boolean('is_external')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('source_page_id');
            $table->index('target_page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
