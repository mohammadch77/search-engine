<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('crawl_queue', function (Blueprint $table) {
            $table->string('locked_by', 64)->nullable()->after('status');
            $table->index(['status', 'domain_id']);
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('crawl_queue', function (Blueprint $table) {
            $table->dropIndex(['status', 'domain_id']);
            $table->dropColumn('locked_by');
        });
    }
};
