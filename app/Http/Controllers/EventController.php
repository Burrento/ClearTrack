<?php

namespace App\Http\Controllers;

use App\Models\Event;
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
        $validated = $this->validateEvent($request);

        $validated['department_id'] = Auth::user()->department_id;
        Event::create($validated);

        return redirect()->route('events.index')->with('status', 'Event created successfully.');
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

        $validated = $this->validateEvent($request);

        $event->update($validated);

        return redirect()->route('events.index')->with('status', 'Event updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event): RedirectResponse
    {
        $this->authorizeDepartment($event->department_id);
        $event->delete();

        return redirect()->route('events.index')->with('status', 'Event deleted successfully.');
    }

    /**
     * Process absences for students who missed the event.
     * Marks absent students so their penalty points accumulate.
     */
    public function processFines(Event $event): RedirectResponse
    {
        $this->authorizeDepartment($event->department_id);

        // Only process events that have ended
        if (Carbon::parse($event->end_time)->isFuture()) {
            return back()->with('error', 'Cannot process — this event has not ended yet.');
        }

        // Prevent double-processing
        if ($event->fines_processed) {
            return back()->with('error', 'Absences have already been processed for this event.');
        }

        $absentCount = $event->markAbsentees();

        return back()->with('status', "Absences processed: {$absentCount} student(s) marked absent. Penalty points ({$event->penalty_points} pts) will be added to their totals.");
    }

    private function validateEvent(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'requires_survey' => 'boolean',
            'penalty_points' => 'required|integer|min:0',
        ]);

        $validated['requires_survey'] = $request->has('requires_survey');

        return $validated;
    }
}
