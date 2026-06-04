<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
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

        // Find events that have ended and haven't been processed yet
        $events = Event::where('end_time', '<=', Carbon::now())
            ->where('fines_processed', false)
            ->get();

        foreach ($events as $event) {
            $this->info("Processing absences for event: {$event->name}");

            // Get all students in the department
            $students = User::role('student')
                ->where('department_id', $event->department_id)
                ->get();

            $absentCount = 0;

            foreach ($students as $student) {
                // Check Attendance
                $attendance = Attendance::where('event_id', $event->id)
                    ->where('user_id', $student->id)
                    ->first();

                if (!$attendance) {
                    // No attendance record at all — mark as absent
                    Attendance::create([
                        'event_id' => $event->id,
                        'user_id' => $student->id,
                        'status' => 'absent',
                    ]);
                    $absentCount++;
                    $this->line("Marked {$student->name} as absent.");
                } elseif ($attendance->status !== 'present') {
                    // Has a record but not marked as present (e.g. pending_verification)
                    $attendance->update(['status' => 'absent']);
                    $absentCount++;
                    $this->line("Marked {$student->name} as absent (was {$attendance->status}).");
                }
            }

            $event->update(['fines_processed' => true]);
            $this->info("Done: {$absentCount} student(s) marked absent for '{$event->name}' ({$event->penalty_points} pts each).");
        }

        $this->info('Absence processing complete.');
    }
}
