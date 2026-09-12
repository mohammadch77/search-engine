<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->bigInteger('price');
            $table->string('price_formatted', 50);
            $table->enum('currency', ['toman', 'rial'])->default('toman');
            $table->string('seller_name', 255)->nullable();
            $table->enum('availability', ['in_stock', 'out_of_stock', 'unknown'])->default('unknown');
            $table->string('product_url', 2048);
            $table->timestamp('extracted_at');
            $table->timestamps();

            $table->index(['product_id', 'price']);
            $table->index('domain_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
