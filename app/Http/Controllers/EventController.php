<?php

namespace App\Http\Controllers;
use App\Models\Event;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Fine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Carbon\Carbon;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $events = Event::where('department_id', Auth::user()->department_id)
            ->orderBy('start_time', 'desc')
            ->get();

        return view('events.index', compact('events'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('events.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'requires_survey' => 'boolean',
            'fine_amount' => 'required|numeric|min:0',
        ]);

        $validated['department_id'] = Auth::user()->department_id;
        $validated['requires_survey'] = $request->has('requires_survey');

        Event::create($validated);

        return redirect()->route('events.index')->with('status', 'Event created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event): View
    {
        $this->authorizeDepartment($event);
        return view('events.show', compact('event'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event): View
    {
        $this->authorizeDepartment($event);
        return view('events.edit', compact('event'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeDepartment($event);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'requires_survey' => 'boolean',
            'fine_amount' => 'required|numeric|min:0',
        ]);

        $validated['requires_survey'] = $request->has('requires_survey');

        $event->update($validated);

        return redirect()->route('events.index')->with('status', 'Event updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event): RedirectResponse
    {
        $this->authorizeDepartment($event);
        $event->delete();

        return redirect()->route('events.index')->with('status', 'Event deleted successfully.');
    }

    /**
     * Process fines for students who missed the event.
     * Marks absent students and issues fines.
     */
    public function processFines(Event $event): RedirectResponse
    {
        $this->authorizeDepartment($event);

        // Only process events that have ended
        if (Carbon::parse($event->end_time)->isFuture()) {
            return back()->with('error', 'Cannot process fines — this event has not ended yet.');
        }

        // Prevent double-processing
        if ($event->fines_processed) {
            return back()->with('error', 'Fines have already been processed for this event.');
        }

        // Get all students in the department
        $students = User::role('student')
            ->where('department_id', $event->department_id)
            ->get();

        $absentCount = 0;
        $fineCount = 0;

        foreach ($students as $student) {
            $needsFine = false;
            $reason = '';

            // Check attendance
            $attendance = Attendance::where('event_id', $event->id)
                ->where('user_id', $student->id)
                ->first();

            if (!$attendance) {
                // No attendance record — mark as absent
                Attendance::create([
                    'event_id' => $event->id,
                    'user_id' => $student->id,
                    'status' => 'absent',
                ]);
                $needsFine = true;
                $reason = 'Missing Attendance';
                $absentCount++;
            } elseif ($attendance->status !== 'present') {
                // Has a record but not present (e.g. pending_verification)
                $attendance->update(['status' => 'absent']);
                $needsFine = true;
                $reason = 'Missing Attendance';
                $absentCount++;
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
                    $fineCount++;
                }
            }
        }

        $event->update(['fines_processed' => true]);

        return back()->with('status', "Fines processed: {$absentCount} student(s) marked absent, {$fineCount} fine(s) issued.");
    }

    private function authorizeDepartment(Event $event)
    {
        if ($event->department_id !== Auth::user()->department_id) {
            abort(403);
        }
    }
}
