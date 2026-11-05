<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
        [$validated, $sessions] = $this->validateEvent($request);

        DB::transaction(function () use ($validated, $sessions) {
            $event = Event::create($validated + ['department_id' => Auth::user()->department_id]);
            $this->saveSessions($event, $sessions);
        });

        return redirect()->route('events.index')->with('status', 'Event created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event): View
    {
        $this->authorizeDepartment($event->department_id);
        return view('events.show', compact('event'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event): View
    {
        $this->authorizeDepartment($event->department_id);
        return view('events.edit', compact('event'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeDepartment($event->department_id);

        [$validated, $sessions] = $this->validateEvent($request, $event);

        DB::transaction(function () use ($event, $validated, $sessions) {
            $event->update($validated);
            $this->saveSessions($event, $sessions);
        });

        return redirect()->route('events.index')->with('status', 'Changes saved.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event): RedirectResponse
    {
        $this->authorizeDepartment($event->department_id);
        $event->delete();

        return redirect()->route('events.index')->with('status', 'Event deleted.');
    }

    /**
     * Process absences for students who missed the event and charge absent, late and missed-tap fines.
     */
    /**
     * Opens the next session's time in right now instead of 1 hour before its start. Taps before
     * the start still count as on time; time out and the other sessions keep their usual windows.
     */
    public function startEarly(Event $event): RedirectResponse
    {
        $this->authorizeDepartment($event->department_id);

        $session = $event->sessions->first(fn ($s) => $s->inOpensAt()->isFuture());

        if ($event->fines_processed || $event->isOpenForAttendance() || !$session) {
            return back()->with('error', 'Attendance is already open or this event has finished.');
        }

        $session->update(['in_opens_at' => now()]);

        return redirect()->route('attendance.rfid', $event)->with('status',
            "{$session->label()} time in is open. Taps before {$session->starts_at->format('g:i A')} count as on time.");
    }

    public function processFines(Event $event): RedirectResponse
    {
        $this->authorizeDepartment($event->department_id);

        // Only after the last session's time out has closed
        if ($event->closesAt()->isFuture()) {
            return back()->with('error', 'Cannot process yet. Time out closes at ' . $event->closesAt()->format('g:i A') . '.');
        }

        // Prevent double-processing
        if ($event->fines_processed) {
            return back()->with('error', 'Absences have already been processed for this event.');
        }

        $c = $event->markAbsentees();

        return back()->with('status', "Absences processed: {$c['absent']} absent, {$c['late']} late, "
            . "{$c['missed_in']} with no time in and {$c['missed_out']} with no time out. Fines were added for each.");
    }

    /**
     * Returns [event fields, sessions keyed by name => [starts_at, ends_at] or null when not held].
     * A form submits one date plus a start/end time per ticked session.
     */
    private function validateEvent(Request $request, ?Event $event = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'requires_survey' => 'boolean',
            'absent_fine' => 'required|numeric|min:0|max:100000',
            'late_fine' => 'required|numeric|min:0|max:100000',
            'missed_in_fine' => 'required|numeric|min:0|max:100000',
            'missed_out_fine' => 'required|numeric|min:0|max:100000',
            'sessions' => 'array',
            'sessions.*.start' => 'nullable|date_format:H:i',
            'sessions.*.end' => 'nullable|date_format:H:i',
        ]);

        $sessions = [];
        $previousEnd = null;

        foreach (EventSession::NAMES as $name) {
            $input = $request->input("sessions.$name", []);

            if (empty($input['enabled'])) {
                if ($event?->sessions->firstWhere('name', $name)?->attendances()->exists()) {
                    throw ValidationException::withMessages(["sessions.$name" => ucfirst($name) . " already has taps, so it can't be removed."]);
                }
                $sessions[$name] = null;
                continue;
            }

            if (empty($input['start']) || empty($input['end'])) {
                throw ValidationException::withMessages(["sessions.$name" => 'Enter a start and end time for ' . $name . '.']);
            }

            $start = Carbon::parse($validated['date'] . ' ' . $input['start']);
            $end = Carbon::parse($validated['date'] . ' ' . $input['end']);

            if ($end->lt($start->copy()->addHour())) {
                throw ValidationException::withMessages(["sessions.$name" => ucfirst($name) . ' must end at least 1 hour after it starts.']);
            }
            if ($previousEnd && $start->lt($previousEnd)) {
                throw ValidationException::withMessages(["sessions.$name" => ucfirst($name) . ' must start after the session before it ends.']);
            }

            $sessions[$name] = ['starts_at' => $start, 'ends_at' => $end];
            $previousEnd = $end;
        }

        $held = array_filter($sessions);
        if (!$held) {
            throw ValidationException::withMessages(['sessions' => 'Choose at least one session.']);
        }

        $validated['start_time'] = reset($held)['starts_at'];
        $validated['end_time'] = end($held)['ends_at'];
        $validated['requires_survey'] = $request->has('requires_survey');
        unset($validated['date'], $validated['sessions']);

        return [$validated, $sessions];
    }

    private function saveSessions(Event $event, array $sessions): void
    {
        foreach ($sessions as $name => $times) {
            $times
                ? $event->sessions()->updateOrCreate(['name' => $name], $times)
                : $event->sessions()->where('name', $name)->delete();
        }
    }
}
