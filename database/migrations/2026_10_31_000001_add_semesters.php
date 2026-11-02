<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_at');
            $table->timestamps();
        });

        Schema::table('clearance_periods', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });

        Schema::table('fines', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_cleared');
        });

        // Existing data belongs to an "Initial semester" so nothing is orphaned
        if (DB::table('clearance_periods')->exists() || DB::table('events')->exists()) {
            $startsAt = DB::table('events')->min('start_time') ?? now();

            $semesterId = DB::table('semesters')->insertGetId([
                'name' => 'Initial semester',
                'starts_at' => date('Y-m-d', strtotime($startsAt)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('clearance_periods')->update(['semester_id' => $semesterId]);
            DB::table('fines')->whereNull('event_id')->update(['semester_id' => $semesterId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('fines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('semester_id');
        });

        Schema::table('clearance_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('semester_id');
        });

        Schema::dropIfExists('semesters');
    }
};
