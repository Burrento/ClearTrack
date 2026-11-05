<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set when an officer ends a session (or the whole event) early: no more taps are recorded
     * for it, and absences can be processed without waiting for its time-out window.
     */
    public function up(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->dateTime('closed_at')->nullable()->after('in_opens_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
