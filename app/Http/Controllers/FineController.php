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
        $departmentId = Auth::user()->department_id;

        // Get fines tied to events in this department OR clearance fines for students in this department
        $fines = Fine::where(function ($query) use ($departmentId) {
            $query->whereHas('event', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            })->orWhere(function ($q) use ($departmentId) {
                $q->whereNull('event_id')
                  ->whereHas('user', function ($u) use ($departmentId) {
                      $u->where('department_id', $departmentId);
                  });
            });
        })->with(['user', 'event'])->orderBy('created_at', 'desc')->get();

        return view('fines.index', compact('fines'));
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

        return back()->with('status', 'Fine marked as paid.');
    }
}
