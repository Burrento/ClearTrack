<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Fine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FineController extends Controller
{
    /**
     * One row per student per event: that student's fines for the event added up, so a payment is
     * recorded once for the whole event. Search, filter and 50 rows a page.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'event' => 'nullable|integer',
            'year' => ['nullable', Rule::in(User::YEAR_LEVELS)],
            'status' => ['nullable', Rule::in(['unpaid', 'paid'])],
        ]);
        $unpaidSum = "SUM(CASE WHEN fines.status = 'unpaid' THEN fines.amount ELSE 0 END)";

        $groups = $this->departmentScope()
            ->join('users', 'users.id', '=', 'fines.user_id')
            ->select('fines.user_id', 'fines.event_id', DB::raw('SUM(fines.amount) as total'), DB::raw("$unpaidSum as unpaid"), DB::raw('MAX(fines.created_at) as latest'))
            ->when($filters['q'] ?? null, fn ($q, $text) => $q->where(fn ($w) => $w->where('users.name', 'like', "%{$text}%")->orWhere('users.username', 'like', "%{$text}%")))
            ->when($filters['event'] ?? null, fn ($q, $id) => $q->where('fines.event_id', $id))
            ->when($filters['year'] ?? null, fn ($q, $year) => $q->where('users.year_level', $year))
            ->when(($filters['status'] ?? null) === 'unpaid', fn ($q) => $q->havingRaw("$unpaidSum > 0"))
            ->when(($filters['status'] ?? null) === 'paid', fn ($q) => $q->havingRaw("$unpaidSum = 0"))
            ->groupBy('fines.user_id', 'fines.event_id', 'users.name')
            ->orderByDesc('latest')->orderBy('users.name')
            ->paginate(50)->withQueryString();

        // The fines behind this page's rows, to list each one's reasons
        $fines = $this->departmentScope()->with('semester')
            ->whereIn('user_id', $groups->pluck('user_id'))
            ->where(fn ($q) => $q->whereIn('event_id', $groups->pluck('event_id')->filter())->orWhereNull('event_id'))
            ->orderBy('id')->get()->groupBy(fn ($f) => $f->user_id . '|' . $f->event_id);
        $users = User::whereIn('id', $groups->pluck('user_id'))->get()->keyBy('id');
        $events = Event::whereIn('id', $groups->pluck('event_id')->filter())->get()->keyBy('id');

        $unpaid = $this->departmentScope()->where('status', 'unpaid');

        return view('fines.index', [
            'groups' => $groups,
            'fines' => $fines,
            'users' => $users,
            'events' => $events,
            'filters' => $filters,
            'unpaidTotal' => (clone $unpaid)->sum('amount'),
            'owingCount' => (clone $unpaid)->distinct()->count('user_id'),
            'eventOptions' => Event::where('department_id', Auth::user()->department_id)->where('fines_processed', true)->latest('start_time')->get(['id', 'name', 'start_time']),
        ]);
    }

    /** Records every unpaid fine of one student for one event (or their old clearance fines) as paid. */
    public function payGroup(Request $request): RedirectResponse
    {
        $validated = $request->validate(['user_id' => 'required|integer', 'event_id' => 'nullable|integer']);

        $fines = $this->departmentScope()->where('status', 'unpaid')->where('user_id', $validated['user_id'])
            ->when($validated['event_id'] ?? null, fn ($q, $id) => $q->where('event_id', $id), fn ($q) => $q->whereNull('event_id'));
        $total = (clone $fines)->sum('amount');
        abort_if((clone $fines)->doesntExist(), 404);
        $fines->update(['status' => 'paid']);

        return back()->with('status', 'Payment of ₱' . number_format($total, 2) . ' recorded for ' . User::find($validated['user_id'])?->name . '.');
    }

    /** Students who still owe (ID, name, status, unpaid amount), as a CSV or a printable page saved as PDF. */
    public function export(string $format)
    {
        $department = Auth::user()->department;
        $students = $this->departmentScope()->with('user')->get()->groupBy('user_id')
            ->map(fn ($fines) => (object) [
                'user' => $fines->first()->user,
                'unpaid' => $fines->where('status', 'unpaid')->sum('amount'),
            ])
            ->filter(fn ($s) => $s->unpaid > 0)
            ->sortBy(fn ($s) => $s->user->name, SORT_NATURAL | SORT_FLAG_CASE)->values();

        if ($format === 'pdf') {
            return view('fines.print', compact('students', 'department'));
        }

        return response()->streamDownload(function () use ($students) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}"); // BOM so Excel reads ñ and ₱
            fputcsv($out, ['Student ID', 'Name', 'Status', 'Amount'], escape: '');
            foreach ($students as $s) {
                fputcsv($out, [$s->user->username, $s->user->name, $s->unpaid > 0 ? 'Owes' : 'Cleared', number_format($s->unpaid, 2, '.', '')], escape: '');
            }
            fclose($out);
        }, 'fines-' . strtolower($department->code) . '-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Fines for events in the officer's department, plus old clearance fines of its students. */
    private function departmentScope()
    {
        $departmentId = Auth::user()->department_id;

        return Fine::where(function ($query) use ($departmentId) {
            $query->whereHas('event', fn ($q) => $q->where('department_id', $departmentId))
                ->orWhere(fn ($q) => $q->whereNull('fines.event_id')
                    ->whereHas('user', fn ($u) => $u->where('department_id', $departmentId)));
        });
    }

    public function markAsPaid(Fine $fine): RedirectResponse
    {
        $departmentId = Auth::user()->department_id;

        // For event-based fines, check event department; for clearance fines, check user department
        if ($fine->event_id) {
            if ($fine->event->department_id !== $departmentId) {
                abort(403);
            }
        } else {
            if ($fine->user->department_id !== $departmentId) {
                abort(403);
            }
        }

        $fine->update(['status' => 'paid']);

        return back()->with('status', 'Payment recorded.');
    }
}
