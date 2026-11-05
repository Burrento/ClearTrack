<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Departments are archived, never deleted: deleting would cascade away their events and attendance.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
