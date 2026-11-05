<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventSession;
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

        // Events whose last time out has closed and that haven't been processed yet
        $events = Event::where('end_time', '<=', now()->subMinutes(EventSession::OUT_WINDOW_MINUTES))
            ->where('fines_processed', false)
            ->get();

        foreach ($events as $event) {
            $c = $event->markAbsentees();
            $this->info("Done: '{$event->name}': {$c['absent']} absent, {$c['late']} late, {$c['missed_in']} no time in, {$c['missed_out']} no time out.");
        }

        $this->info('Absence processing complete.');
    }
}
