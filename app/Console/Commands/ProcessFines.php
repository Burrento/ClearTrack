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

        // Events that have ended and haven't been processed yet
        $events = Event::where('end_time', '<=', now())
            ->where('fines_processed', false)
            ->get();

        foreach ($events as $event) {
            $absentCount = $event->markAbsentees();
            $this->info("Done: {$absentCount} student(s) marked absent for '{$event->name}' ({$event->penalty_points} pts each).");
        }

        $this->info('Absence processing complete.');
    }
}
