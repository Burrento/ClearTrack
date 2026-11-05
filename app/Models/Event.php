<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * An event day with one to three sessions (see EventSession). start_time / end_time span all
 * sessions and are kept in step by EventController.
 */
#[Fillable(['department_id', 'name', 'start_time', 'end_time', 'absent_fine', 'late_fine', 'missed_in_fine', 'missed_out_fine', 'requires_survey', 'fines_processed'])]
class Event extends Model
{
    protected static function booted(): void
    {
        // An event created without sessions (older code paths, tests) gets one spanning its times
        static::created(function (Event $event) {
            $event->sessions()->create([
                'name' => EventSession::nameFor($event->start_time),
                'starts_at' => $event->start_time,
                'ends_at' => $event->end_time,
            ]);
        });
    }

    public function sessions()
    {
        return $this->hasMany(EventSession::class)->orderBy('starts_at');
    }

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
            'missed_in_fine' => 'decimal:2',
            'missed_out_fine' => 'decimal:2',
            'requires_survey' => 'boolean',
            'fines_processed' => 'boolean',
        ];
    }

    public function checkInOpensAt()
    {
        return $this->start_time->copy()->subMinutes(EventSession::IN_OPENS_MINUTES_BEFORE);
    }

    /**
     * When the last session's time out closes; absences can be processed after this.
     */
    public function closesAt()
    {
        return $this->end_time->copy()->addMinutes(EventSession::OUT_WINDOW_MINUTES);
    }

    /**
     * A time-in or time-out window is open now, and absences haven't been processed.
     */
    public function isOpenForAttendance(): bool
    {
        return !$this->fines_processed && $this->sessions->contains(fn ($s) => $s->isInOpen() || $s->isOutOpen());
    }

    /**
     * What a tap by this student right now records: [session, 'in' | 'out' | 'repeat'], or null
     * when no window is open. Where one session's time out overlaps the next one's time in, a
     * student who is tapped in and not out yet is tapping out.
     */
    public function resolveTap(User $student): ?array
    {
        $rows = $this->attendances()->where('user_id', $student->id)->get()->keyBy('event_session_id');
        $open = $this->sessions->filter(fn ($s) => $s->isInOpen() || $s->isOutOpen());

        foreach ($open as $s) {
            if ($s->isOutOpen() && $rows->get($s->id)?->time_in_at && !$rows->get($s->id)->time_out_at) return [$s, 'out'];
        }
        foreach ($open as $s) {
            if ($s->isInOpen() && !$rows->get($s->id)?->time_in_at) return [$s, 'in'];
        }
        foreach ($open as $s) {
            // Tapping out without a time in, unless the student has already moved on to a later session
            $movedOn = $this->sessions->contains(fn ($later) => $later->starts_at->gt($s->starts_at) && $rows->has($later->id));
            if ($s->isOutOpen() && !$rows->get($s->id)?->time_out_at && !$movedOn) return [$s, 'out'];
        }

        // Nothing new to record: report the open session the student last tapped into
        return $open->isEmpty() ? null : [$open->filter(fn ($s) => $rows->has($s->id))->last() ?? $open->first(), 'repeat'];
    }

    /**
     * Where the event is in its life: upcoming, live, ended (absences not yet processed) or processed.
     */
    public function phase(): string
    {
        return match (true) {
            $this->fines_processed => 'processed',
            $this->checkInOpensAt()->isFuture() => 'upcoming',
            $this->closesAt()->isPast() => 'ended',
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
     * e.g. "Fri, Nov 6: Morning 8:00 AM – 12:00 PM, Afternoon 1:00 PM – 5:00 PM".
     */
    public function schedule(): string
    {
        return $this->start_time->format('D, M j') . ': '
            . $this->sessions->map(fn ($s) => $s->label() . ' ' . $s->times())->implode(', ');
    }

    /**
     * After the last time out closes: for every active department student and every session,
     * mark no taps as absent and tap-out-only as missed_in, then fine absent, late, missed time in
     * and missed time out with this event's amounts. Runs once per event. Returns counts per kind.
     */
    public function markAbsentees(): array
    {
        return DB::transaction(function () {
            $students = User::role('student')->where('department_id', $this->department_id)->where('is_active', true)->get();
            $rows = $this->attendances()->get()->groupBy('event_session_id');
            $semesterId = Semester::current()?->id;
            $counts = ['absent' => 0, 'late' => 0, 'missed_in' => 0, 'missed_out' => 0];

            $fine = function (User $student, string $kind, EventSession $session) use ($semesterId, &$counts) {
                $counts[$kind]++;
                $amount = $this->{$kind . '_fine'};
                if ($amount > 0) {
                    Fine::create([
                        'user_id' => $student->id,
                        'event_id' => $this->id,
                        'semester_id' => $semesterId,
                        'amount' => $amount,
                        'status' => 'unpaid',
                        'reason' => ['absent' => 'Absent', 'late' => 'Late', 'missed_in' => 'No time in', 'missed_out' => 'No time out'][$kind]
                            . ' (' . strtolower($session->label()) . ')',
                    ]);
                }
            };

            foreach ($this->sessions as $session) {
                $sessionRows = ($rows->get($session->id) ?? collect())->keyBy('user_id');

                foreach ($students as $student) {
                    $row = $sessionRows->get($student->id);

                    if (!$row || (!$row->time_in_at && !$row->time_out_at)) {
                        $row
                            ? $row->update(['status' => 'absent'])
                            : $this->attendances()->create(['event_session_id' => $session->id, 'user_id' => $student->id, 'status' => 'absent']);
                        $fine($student, 'absent', $session);
                        continue;
                    }

                    if (!$row->time_in_at) {
                        $row->update(['status' => 'missed_in']);
                        $fine($student, 'missed_in', $session);
                    } elseif ($row->status === 'late') {
                        $fine($student, 'late', $session);
                    }

                    if (!$row->time_out_at) {
                        $fine($student, 'missed_out', $session);
                    }
                }
            }

            $this->update(['fines_processed' => true]);

            return $counts;
        });
    }
}
