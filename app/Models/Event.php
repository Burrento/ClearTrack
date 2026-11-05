<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['department_id', 'name', 'start_time', 'end_time', 'absent_fine', 'late_fine', 'requires_survey', 'fines_processed'])]
class Event extends Model
{
    /**
     * Scanning opens this long before the start so on-time students can tap in; any tap after
     * the start time counts as late.
     */
    public const CHECK_IN_OPENS_MINUTES_BEFORE = 60;

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
            'absent_fine' => 'decimal:2',
            'late_fine' => 'decimal:2',
            'requires_survey' => 'boolean',
            'fines_processed' => 'boolean',
        ];
    }

    public function checkInOpensAt()
    {
        return $this->start_time->copy()->subMinutes(self::CHECK_IN_OPENS_MINUTES_BEFORE);
    }

    /**
     * Attendance can be recorded from check-in opening until the end, before absences are processed.
     */
    public function isOpenForAttendance(): bool
    {
        return !$this->fines_processed && now()->between($this->checkInOpensAt(), $this->end_time);
    }

    /**
     * The attendance status a tap made right now records.
     */
    public function tapStatus(): string
    {
        return now()->gt($this->start_time) ? 'late' : 'present';
    }

    /**
     * Where the event is in its life: upcoming, live, ended (absences not yet processed) or processed.
     */
    public function phase(): string
    {
        return match (true) {
            $this->fines_processed => 'processed',
            $this->checkInOpensAt()->isFuture() => 'upcoming',
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
     * Mark every department student who didn't tap in as absent, fine absent and late students
     * this event's amounts, and flag the event as processed. Returns [absent count, late count].
     */
    public function markAbsentees(): array
    {
        return DB::transaction(function () {
            $attendances = $this->attendances()->get()->keyBy('user_id');
            $students = User::role('student')->where('department_id', $this->department_id)->where('is_active', true)->get();
            $semesterId = Semester::current()?->id;
            $counts = ['absent' => 0, 'late' => 0];

            foreach ($students as $student) {
                $attendance = $attendances->get($student->id);

                if (!$attendance) {
                    $attendance = $this->attendances()->create(['user_id' => $student->id, 'status' => 'absent']);
                } elseif (!in_array($attendance->status, ['present', 'late'])) {
                    $attendance->update(['status' => 'absent']); // leftover pending_verification
                }

                if ($attendance->status === 'present') {
                    continue;
                }

                $counts[$attendance->status]++;
                $amount = $attendance->status === 'late' ? $this->late_fine : $this->absent_fine;

                if ($amount > 0) {
                    Fine::create([
                        'user_id' => $student->id,
                        'event_id' => $this->id,
                        'semester_id' => $semesterId,
                        'amount' => $amount,
                        'status' => 'unpaid',
                        'reason' => ucfirst($attendance->status),
                    ]);
                }
            }

            $this->update(['fines_processed' => true]);

            return [$counts['absent'], $counts['late']];
        });
    }
}
