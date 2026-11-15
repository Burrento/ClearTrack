<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Students an officer excused from an event's fines, with the reason. Their fines for that
     * event get the new "exempted" status, so fines.status becomes a plain string.
     */
    public function up(): void
    {
        Schema::create('fine_exemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::table('fines', function (Blueprint $table) {
            $table->string('status', 20)->default('unpaid')->change();
        });
    }

    public function down(): void
    {
        DB::table('fines')->where('status', 'exempted')->update(['status' => 'unpaid']);
        Schema::table('fines', function (Blueprint $table) {
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid')->change();
        });

        Schema::dropIfExists('fine_exemptions');
    }
};
