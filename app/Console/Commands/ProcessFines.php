<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use App\Models\Fine;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cleartrack:process-fines')]
#[Description('Automatically process fines for students who missed events or surveys.')]
class ProcessFines extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting fine processing...');

        // Find events that have ended and haven't been processed yet
        $events = Event::where('end_time', '<=', Carbon::now())
            ->where('fines_processed', false)
            ->get();

        foreach ($events as $event) {
            $this->info("Processing fines for event: {$event->name}");

            // Get all students in the department
            $students = User::role('student')
                ->where('department_id', $event->department_id)
                ->get();

            foreach ($students as $student) {
                $needsFine = false;
                $reason = '';

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
                    $needsFine = true;
                    $reason = 'Missing Attendance';
                    $this->line("Marked {$student->name} as absent.");
                } elseif ($attendance->status !== 'present') {
                    // Has a record but not marked as present (e.g. pending_verification)
                    $attendance->update(['status' => 'absent']);
                    $needsFine = true;
                    $reason = 'Missing Attendance';
                    $this->line("Marked {$student->name} as absent (was {$attendance->status}).");
                }

                // Check Survey (if required and student was present)
                if (!$needsFine && $event->requires_survey && $event->survey) {
                    $surveyCompleted = $student->surveyResponses()
                        ->whereIn('survey_question_id', $event->survey->questions->pluck('id'))
                        ->exists();

                    if (!$surveyCompleted) {
                        $needsFine = true;
                        $reason = 'Missing Survey Completion';
                    }
                }

                if ($needsFine && $event->fine_amount > 0) {
                    // Avoid duplicate fines
                    $existingFine = Fine::where('user_id', $student->id)
                        ->where('event_id', $event->id)
                        ->exists();

                    if (!$existingFine) {
                        Fine::create([
                            'user_id' => $student->id,
                            'event_id' => $event->id,
                            'amount' => $event->fine_amount,
                            'status' => 'unpaid',
                            'reason' => $reason,
                        ]);
                        $this->line("Fine issued to {$student->name} for {$reason}");
                    }
                }
            }

            $event->update(['fines_processed' => true]);
        }

        $this->info('Fine processing complete.');
    }
}
