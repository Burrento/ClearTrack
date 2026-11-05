<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which year levels an event is for, e.g. ["Fourth Year"]; null means every year level.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('year_levels')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('year_levels');
        });
    }
};
