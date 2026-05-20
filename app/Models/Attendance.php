<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_id', 'user_id', 'status', 'photo_path', 'verified_by_id'])]
class Attendance extends Model
{
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
}
