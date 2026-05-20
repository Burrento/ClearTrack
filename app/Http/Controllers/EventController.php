<?php

namespace App\Http\Controllers;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
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

    private function authorizeDepartment(Event $event)
    {
        if ($event->department_id !== Auth::user()->department_id) {
            abort(403);
        }
    }
}
