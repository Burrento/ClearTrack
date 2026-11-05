<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cleartrack:process-fines')]
#[Description('Automatically process absences for students who missed events.')]
class ProcessFines extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting absence processing...');

        // Unprocessed events whose sessions have all stopped taking taps (time out over, or ended early)
        $events = Event::where('fines_processed', false)->where('start_time', '<=', now())
            ->with('sessions')->get()
            ->filter(fn (Event $event) => $event->closesAt()->isPast());

        foreach ($events as $event) {
            $c = $event->markAbsentees();
            $this->info("Done: '{$event->name}': {$c['absent']} absent, {$c['late']} late, {$c['missed_in']} no time in, {$c['missed_out']} no time out.");
        }

        $this->info('Absence processing complete.');
    }
}
