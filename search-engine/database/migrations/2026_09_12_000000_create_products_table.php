<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500);
            $table->string('name_normalized', 500);
            $table->string('category', 255)->nullable();
            $table->string('brand', 255)->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE products ADD FULLTEXT fulltext_name (name, name_normalized)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
