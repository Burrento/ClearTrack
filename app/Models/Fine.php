<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'event_id', 'semester_id', 'amount', 'status', 'reason', 'paid_at', 'paid_by_id'])]
class Fine extends Model
{
    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    /** The officer who recorded the payment (empty for payments recorded before this was kept). */
    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
