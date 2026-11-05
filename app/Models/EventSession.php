<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One part of an event day (morning, afternoon or evening) that students tap in to and out of.
 *
 * Time in:  from 1 hour before the start (or earlier, if an officer started attendance early) until
 *           30 min before the end; after the start it's late.
 * Time out: from 30 min before the end until 30 min after it.
 * Ending early sets closed_at: both windows shut at once and no more taps are recorded.
 */
#[Fillable(['event_id', 'name', 'starts_at', 'ends_at', 'in_opens_at', 'closed_at'])]
class EventSession extends Model
{
    public const NAMES = ['morning', 'afternoon', 'evening'];
    public const IN_OPENS_MINUTES_BEFORE = 60;
    public const OUT_WINDOW_MINUTES = 30; // either side of the end

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'in_opens_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Which session a start time belongs to; used for events created without explicit sessions.
     */
    public static function nameFor(CarbonInterface $start): string
    {
        return match (true) {
            $start->hour < 12 => 'morning',
            $start->hour < 17 => 'afternoon',
            default => 'evening',
        };
    }

    public function label(): string
    {
        return ucfirst($this->name);
    }

    public function inOpensAt(): CarbonInterface
    {
        $usual = $this->starts_at->copy()->subMinutes(self::IN_OPENS_MINUTES_BEFORE);

        return $this->in_opens_at?->lt($usual) ? $this->in_opens_at->copy() : $usual;
    }

    public function outOpensAt(): CarbonInterface
    {
        return $this->ends_at->copy()->subMinutes(self::OUT_WINDOW_MINUTES);
    }

    public function outClosesAt(): CarbonInterface
    {
        return $this->ends_at->copy()->addMinutes(self::OUT_WINDOW_MINUTES);
    }

    public function isInOpen(): bool
    {
        return !$this->closed_at && now()->gte($this->inOpensAt()) && now()->lt($this->outOpensAt());
    }

    public function isOutOpen(): bool
    {
        return !$this->closed_at && now()->between($this->outOpensAt(), $this->outClosesAt());
    }

    /**
     * When no more taps can be recorded: when it was ended early, or when its time out closes.
     */
    public function closesAt(): CarbonInterface
    {
        return $this->closed_at?->copy() ?? $this->outClosesAt();
    }

    /**
     * Time in hasn't opened yet, so an officer can open it now.
     */
    public function canStartNow(): bool
    {
        return !$this->closed_at && $this->inOpensAt()->isFuture();
    }

    /**
     * Taps are being recorded, so an officer can end it now: it closes at once, with no time-out
     * window. Students who haven't tapped out get the "no time out" fine when absences are processed.
     */
    public function canEndNow(): bool
    {
        return $this->isInOpen() || $this->isOutOpen();
    }

    /**
     * upcoming, in (time in open), out (time out open), closed (ended early) or ended
     */
    public function state(): string
    {
        return match (true) {
            (bool) $this->closed_at => 'closed',
            $this->isOutOpen() => 'out',
            $this->isInOpen() => 'in',
            $this->canStartNow() => 'upcoming',
            default => 'ended',
        };
    }

    /**
     * "8:00 AM – 12:00 PM"
     */
    public function times(): string
    {
        return $this->starts_at->format('g:i A') . ' – ' . $this->ends_at->format('g:i A');
    }
}
