<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a session takes a time in and/or a time out. Existing sessions take both.
     */
    public function up(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->boolean('has_in')->default(true)->after('ends_at');
            $table->boolean('has_out')->default(true)->after('has_in');
        });
    }

    public function down(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->dropColumn(['has_in', 'has_out']);
        });
    }
};
