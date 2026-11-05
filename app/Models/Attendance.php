<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One student in one event session. status is "present" or "late" from the time-in tap, or set
 * when absences are processed: "absent" (no taps) or "missed_in" (only tapped out).
 */
#[Fillable(['event_id', 'event_session_id', 'user_id', 'status', 'time_in_at', 'time_out_at', 'photo_path', 'verified_by_id'])]
class Attendance extends Model
{
    protected function casts(): array
    {
        return ['time_in_at' => 'datetime', 'time_out_at' => 'datetime'];
    }

    /**
     * Get the user that owns the attendance.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the event that the attendance belongs to.
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function session()
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }
}
