<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One part of an event day (morning, afternoon or evening) that students tap in to and out of.
 *
 * Time in:  from 1 hour before the start until 30 min before the end; after the start it's late.
 * Time out: from 30 min before the end until 30 min after it.
 */
#[Fillable(['event_id', 'name', 'starts_at', 'ends_at'])]
class EventSession extends Model
{
    public const NAMES = ['morning', 'afternoon', 'evening'];
    public const IN_OPENS_MINUTES_BEFORE = 60;
    public const OUT_WINDOW_MINUTES = 30; // either side of the end

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
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
        return $this->starts_at->copy()->subMinutes(self::IN_OPENS_MINUTES_BEFORE);
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
        return now()->gte($this->inOpensAt()) && now()->lt($this->outOpensAt());
    }

    public function isOutOpen(): bool
    {
        return now()->between($this->outOpensAt(), $this->outClosesAt());
    }

    /**
     * "8:00 AM – 12:00 PM"
     */
    public function times(): string
    {
        return $this->starts_at->format('g:i A') . ' – ' . $this->ends_at->format('g:i A');
    }
}
