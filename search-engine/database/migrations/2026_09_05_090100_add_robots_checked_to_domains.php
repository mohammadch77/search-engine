<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('robots_checked')->default(false)->after('robots_txt');
        });

        // Existing domains already have robots_txt populated (or genuinely unreachable).
        \DB::table('domains')->update(['robots_checked' => true]);
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('robots_checked');
        });
    }
};
