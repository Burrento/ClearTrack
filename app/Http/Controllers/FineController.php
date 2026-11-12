<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FineController extends Controller
{
    public function index(): View
    {
        return view('fines.index', ['fines' => $this->departmentFines()]);
    }

    /** Students who still owe (ID, name, status, unpaid amount), as a CSV or a printable page saved as PDF. */
    public function export(string $format)
    {
        $department = Auth::user()->department;
        $students = $this->departmentFines()->groupBy('user_id')
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
    private function departmentFines()
    {
        $departmentId = Auth::user()->department_id;

        return Fine::where(function ($query) use ($departmentId) {
            $query->whereHas('event', fn ($q) => $q->where('department_id', $departmentId))
                ->orWhere(fn ($q) => $q->whereNull('event_id')
                    ->whereHas('user', fn ($u) => $u->where('department_id', $departmentId)));
        })->with(['user', 'event', 'semester'])->orderBy('created_at', 'desc')->get();
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
