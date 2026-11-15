<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who processed an event's absences and when (no one = the scheduled command), and who
     * recorded a fine as paid and when. Existing rows stay empty: shown as unknown.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('fines_processed_at')->nullable()->after('fines_processed');
            $table->foreignId('fines_processed_by_id')->nullable()->after('fines_processed_at')->constrained('users')->nullOnDelete();
        });
        Schema::table('fines', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->foreignId('paid_by_id')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by_id');
            $table->dropColumn('paid_at');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fines_processed_by_id');
            $table->dropColumn('fines_processed_at');
        });
    }
};
