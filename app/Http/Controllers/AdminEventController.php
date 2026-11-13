<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The admin's view of every department's events, and the one way to change an event after its
 * absences were processed: fix its session ticks or fine amounts and charge it again.
 */
class AdminEventController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'department' => 'nullable|integer']);

        $events = Event::with(['department', 'sessions'])
            ->when($filters['q'] ?? null, fn ($q, $text) => $q->where('name', 'like', "%{$text}%"))
            ->when($filters['department'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->latest('start_time')->paginate(30)->withQueryString();

        return view('admin.events.index', ['events' => $events, 'filters' => $filters, 'departments' => Department::orderBy('code')->get()]);
    }

    public function edit(Event $event): View
    {
        abort_unless($event->fines_processed, 404);

        return view('admin.events.fix', ['event' => $event->load(['department', 'sessions'])]);
    }

    public function reprocess(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->fines_processed, 404);

        $validated = $request->validate([
            'late_fine' => 'required|numeric|min:0|max:100000',
            'missed_in_fine' => 'required|numeric|min:0|max:100000',
            'missed_out_fine' => 'required|numeric|min:0|max:100000',
            'sessions' => 'array',
        ]);

        foreach ($event->sessions as $session) {
            $taps = ['has_in' => $request->boolean("sessions.{$session->id}.has_in"), 'has_out' => $request->boolean("sessions.{$session->id}.has_out")];
            if (!$taps['has_in'] && !$taps['has_out']) {
                throw ValidationException::withMessages(["sessions.{$session->id}" => 'Tick time in, time out or both for ' . strtolower($session->label()) . '.']);
            }
            $session->update($taps);
        }

        $event->update($request->only('late_fine', 'missed_in_fine', 'missed_out_fine'));
        [$counts, $refunds] = $event->fresh()->reprocess();

        $fines = $event->fines()->where('status', 'unpaid');

        return redirect()->route('admin.events.edit', $event)
            ->with('status', "{$event->name} was charged again: {$fines->count()} unpaid fines, ₱" . number_format($fines->sum('amount'), 2) . ' in total.')
            ->with('refunds', $refunds->map(fn ($fine) => [
                'name' => $fine->user?->name, 'username' => $fine->user?->username, 'reason' => $fine->reason, 'amount' => $fine->amount,
            ])->all());
    }
}
