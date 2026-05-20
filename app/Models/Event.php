<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['department_id', 'name', 'start_time', 'end_time', 'requires_survey', 'fine_amount'])]
class Event extends Model
{
    /**
     * Get the attendances for the event.
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get the survey for the event.
     */
    public function survey()
    {
        return $this->hasOne(Survey::class);
    }
}
