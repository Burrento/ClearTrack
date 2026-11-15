<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\FineExemption;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Officers excuse students from an event's fines (performers, excuse letters). Exempting after the
 * fines were charged marks that student's unpaid fines for the event "exempted"; removing the
 * exemption makes them unpaid again.
 */
class FineExemptionController extends Controller
{
    public function index(Request $request): View
    {
        $departmentId = Auth::user()->department_id;
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'event' => 'nullable|integer']);

        $exemptions = FineExemption::with(['user', 'event', 'createdBy'])
            ->whereHas('event', fn ($q) => $q->where('department_id', $departmentId))
            ->when($filters['q'] ?? null, fn ($q, $text) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$text}%")->orWhere('username', 'like', "%{$text}%")))
            ->when($filters['event'] ?? null, fn ($q, $id) => $q->where('event_id', $id))
            ->latest()->paginate(50)->withQueryString();

        return view('exemptions.index', [
            'exemptions' => $exemptions,
            'filters' => $filters,
            'events' => Event::where('department_id', $departmentId)->latest('start_time')->get(['id', 'name', 'start_time']),
            'students' => User::role('student')->where('department_id', $departmentId)->where('is_active', true)->orderBy('name')->get(['username', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'student_id' => 'required|string|max:50',
            'reason' => 'required|string|max:255',
        ]);

        $event = Event::findOrFail($validated['event_id']);
        $this->authorizeDepartment($event->department_id);

        $student = User::role('student')->where('department_id', Auth::user()->department_id)
            ->where('username', strtoupper(trim($validated['student_id'])))->first();
        if (!$student) {
            return back()->withInput()->withErrors(['student_id' => 'No student in your department has this ID.']);
        }
        if ($event->exemptions()->where('user_id', $student->id)->exists()) {
            return back()->withInput()->withErrors(['student_id' => "{$student->name} is already exempted from this event."]);
        }

        $event->exemptions()->create(['user_id' => $student->id, 'reason' => trim($validated['reason']), 'created_by_id' => Auth::id()]);

        $fines = $event->fines()->where('user_id', $student->id)->where('status', 'unpaid');
        $count = (clone $fines)->count();
        $total = (clone $fines)->sum('amount');
        $fines->update(['status' => 'exempted']);

        return redirect()->route('exemptions.index')->with('status', "{$student->name} is exempted from {$event->name}."
            . ($count ? " {$count} " . ($count === 1 ? 'fine' : 'fines') . ' (₱' . number_format($total, 2) . ') marked exempted.' : ''));
    }

    public function destroy(FineExemption $exemption): RedirectResponse
    {
        $this->authorizeDepartment($exemption->event->department_id);

        $count = $exemption->event->fines()->where('user_id', $exemption->user_id)->where('status', 'exempted')->update(['status' => 'unpaid']);
        $exemption->delete();

        return redirect()->route('exemptions.index')->with('status', "{$exemption->user->name} is no longer exempted from {$exemption->event->name}."
            . ($count ? " {$count} " . ($count === 1 ? 'fine is' : 'fines are') . ' unpaid again.' : ''));
    }
}
