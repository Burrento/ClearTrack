<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('fine_amount', 'penalty_points');
        });

        // Change column type from decimal to integer
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('penalty_points')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('penalty_points', 8, 2)->default(0.00)->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('penalty_points', 'fine_amount');
        });
    }
};
