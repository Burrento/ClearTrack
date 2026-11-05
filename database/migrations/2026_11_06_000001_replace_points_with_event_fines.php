<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penalty points and fine tiers are replaced by a fine per event (absent / late), charged when
     * the event's absences are processed. Clearance is now derived: no unpaid fines means cleared.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('absent_fine', 8, 2)->default(0)->after('end_time');
            $table->decimal('late_fine', 8, 2)->default(0)->after('absent_fine');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('penalty_points');
        });

        // "late" joins present/absent; a plain string avoids altering the enum per database
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('status', 20)->default('absent')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_cleared');
        });

        Schema::dropIfExists('clearance_periods');
    }

    public function down(): void
    {
        Schema::create('clearance_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('inactive');
            $table->json('fine_tiers')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_cleared')->default(false);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->integer('penalty_points')->default(1);
            $table->dropColumn(['absent_fine', 'late_fine']);
        });
    }
};
