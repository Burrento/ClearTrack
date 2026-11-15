<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A student excused from one event's fines, with the officer's reason. Processing skips them, and
 * their fines for the event (if already charged) have the "exempted" status.
 */
#[Fillable(['event_id', 'user_id', 'reason', 'created_by_id'])]
class FineExemption extends Model
{
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
