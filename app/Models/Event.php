<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['department_id', 'name', 'start_time', 'end_time', 'requires_survey', 'penalty_points', 'fines_processed'])]
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

    /**
     * Mark every department student without a "present" attendance as absent,
     * flag the event as processed, and return how many were marked.
     */
    public function markAbsentees(): int
    {
        $attendances = $this->attendances()->get()->keyBy('user_id');
        $absentCount = 0;

        $students = User::role('student')->where('department_id', $this->department_id)->get();

        foreach ($students as $student) {
            $attendance = $attendances->get($student->id);

            if (!$attendance) {
                $this->attendances()->create(['user_id' => $student->id, 'status' => 'absent']);
                $absentCount++;
            } elseif ($attendance->status !== 'present') {
                // e.g. pending_verification
                $attendance->update(['status' => 'absent']);
                $absentCount++;
            }
        }

        $this->update(['fines_processed' => true]);

        return $absentCount;
    }
}
