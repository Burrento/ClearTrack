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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'requires_survey' => 'boolean',
            'fines_processed' => 'boolean',
        ];
    }

    /**
     * Attendance can only be recorded while the event is running and before absences are processed.
     */
    public function isOpenForAttendance(): bool
    {
        return !$this->fines_processed && now()->between($this->start_time, $this->end_time);
    }

    /**
     * Where the event is in its life: upcoming, live, ended (absences not yet processed) or processed.
     */
    public function phase(): string
    {
        return match (true) {
            $this->fines_processed => 'processed',
            $this->start_time->isFuture() => 'upcoming',
            $this->end_time->isPast() => 'ended',
            default => 'live',
        };
    }

    /**
     * End time, with the date only when it isn't today, e.g. "12:26 PM" or "Oct 31, 12:26 PM".
     */
    public function endsAt(): string
    {
        return $this->end_time->format($this->end_time->isToday() ? 'g:i A' : 'M j, g:i A');
    }

    /**
     * Human-readable time range, e.g. "Fri, Oct 31, 8:00 AM – 10:00 AM".
     */
    public function schedule(): string
    {
        return $this->start_time->format('D, M j, g:i A') . ' – '
            . $this->end_time->format($this->start_time->isSameDay($this->end_time) ? 'g:i A' : 'D, M j, g:i A');
    }

    /**
     * Mark every department student without a "present" attendance as absent,
     * flag the event as processed, and return how many were marked.
     */
    public function markAbsentees(): int
    {
        $attendances = $this->attendances()->get()->keyBy('user_id');
        $absentCount = 0;

        $students = User::role('student')->where('department_id', $this->department_id)->where('is_active', true)->get();

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
