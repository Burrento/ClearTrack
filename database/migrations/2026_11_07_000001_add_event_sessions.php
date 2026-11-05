<?php

use App\Models\EventSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An event day has up to three sessions (morning, afternoon, evening), each tapped in and out.
     * Existing events become one session each, and their taps become that session's time in.
     */
    public function up(): void
    {
        Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 20); // morning, afternoon or evening
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
            $table->unique(['event_id', 'name']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->decimal('missed_in_fine', 8, 2)->default(0)->after('late_fine');
            $table->decimal('missed_out_fine', 8, 2)->default(0)->after('missed_in_fine');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('event_session_id')->nullable()->after('event_id')->constrained()->cascadeOnDelete();
            $table->timestamp('time_in_at')->nullable()->after('status');
            $table->timestamp('time_out_at')->nullable()->after('time_in_at');
        });

        foreach (DB::table('events')->get() as $event) {
            $sessionId = DB::table('event_sessions')->insertGetId([
                'event_id' => $event->id,
                'name' => EventSession::nameFor(Carbon::parse($event->start_time)),
                'starts_at' => $event->start_time,
                'ends_at' => $event->end_time,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('attendances')->where('event_id', $event->id)->update(['event_session_id' => $sessionId]);
            DB::table('attendances')->where('event_id', $event->id)->whereIn('status', ['present', 'late'])
                ->update(['time_in_at' => DB::raw('created_at')]);
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(['event_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        // MySQL needs the foreign key gone before the unique index it uses
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['event_session_id']);
            $table->dropUnique(['event_session_id', 'user_id']);
            $table->dropColumn(['event_session_id', 'time_in_at', 'time_out_at']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['missed_in_fine', 'missed_out_fine']);
        });

        Schema::dropIfExists('event_sessions');
    }
};
