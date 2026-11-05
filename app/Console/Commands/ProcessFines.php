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
            [$absent, $late] = $event->markAbsentees();
            $this->info("Done: '{$event->name}': {$absent} absent, {$late} late, fines added.");
        }

        $this->info('Absence processing complete.');
    }
}
