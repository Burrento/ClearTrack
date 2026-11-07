<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who did what and when, written by App\Http\Middleware\LogActivity for every action that
     * changes something (plus logins, failed logins and backup downloads). Shown on the admin's Logs page.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null: failed login
            $table->string('action');       // e.g. "Registered a student"
            $table->string('subject')->nullable(); // e.g. "Santos, Ana (LGC26-6578)"
            $table->text('details')->nullable();   // the confirmation message the user saw
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
